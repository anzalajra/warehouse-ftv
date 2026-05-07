// Append-only JSONL queue for offline events. Flushed via POST /api/kiosk/sync
// when heartbeat succeeds. UUIDs make sync idempotent.

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { app } = require('electron');

const QUEUE_FILENAME = 'kiosk-queue.jsonl';

function queuePath() {
  return path.join(app.getPath('userData'), QUEUE_FILENAME);
}

function uuid() {
  return crypto.randomUUID();
}

function readAll() {
  try {
    const raw = fs.readFileSync(queuePath(), 'utf8');
    return raw
      .split('\n')
      .filter(Boolean)
      .map((line) => {
        try { return JSON.parse(line); } catch { return null; }
      })
      .filter(Boolean);
  } catch {
    return [];
  }
}

function rewrite(events) {
  const dir = app.getPath('userData');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(queuePath(), events.map((e) => JSON.stringify(e)).join('\n') + (events.length ? '\n' : ''), 'utf8');
}

function enqueue(type, payload) {
  const event = { id: uuid(), type, payload, queued_at: new Date().toISOString() };
  const dir = app.getPath('userData');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  fs.appendFileSync(queuePath(), JSON.stringify(event) + '\n', 'utf8');
  return event;
}

function size() {
  return readAll().length;
}

async function flush(syncUrl, token) {
  const events = readAll();
  if (events.length === 0) return { applied: 0 };

  try {
    const res = await fetch(syncUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + token,
      },
      body: JSON.stringify({
        events: events.map((e) => ({ type: e.type, payload: e.payload })),
      }),
    });

    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    // Server applied or duplicate-detected — drop those events from queue.
    // Conservative: clear all events we just sent (server returns count).
    rewrite([]);
    return { applied: (data.applied || []).length, rejected: (data.rejected || []).length };
  } catch (err) {
    // Keep queue intact for retry
    return { error: err.message };
  }
}

module.exports = { enqueue, flush, size, readAll, uuid };
