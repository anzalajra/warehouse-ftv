@props(['head' => []])

{{--
    Sortable report table. Clicking a <th> sorts the tbody rows by that column
    (client-side, toggling asc/desc). Numeric detection is currency-aware:
    money is always formatted "Rp 1.000.000,00" (comma decimal), while
    percentages/counts use a plain dot decimal — handled in num() below.
    Empty-state rows (any <td colspan>) are skipped.
--}}
<div class="overflow-x-auto" x-data="{
    dir: {},
    num(s) {
        let t = s.replace(/[%\s]/g, '').replace(/rp/ig, '');
        if (t.includes(',')) { t = t.replace(/\./g, '').replace(',', '.'); }
        return parseFloat(t.replace(/[^0-9.\-]/g, ''));
    },
    sortBy(i) {
        const tbody = $el.querySelector('tbody');
        if (! tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'))
            .filter(r => ! r.querySelector('[colspan]') && r.children.length > i);
        if (rows.length < 2) return;

        const asc = this.dir[i] === true ? false : true;
        this.dir = {}; this.dir[i] = asc;

        const val = (r) => (r.children[i]?.innerText || '').trim();
        const numeric = rows.some(r => ! isNaN(this.num(val(r))))
            && rows.every(r => val(r) === '' || ! isNaN(this.num(val(r))));

        rows.sort((a, b) => {
            const x = val(a), y = val(b);
            if (numeric) {
                const nx = isNaN(this.num(x)) ? 0 : this.num(x);
                const ny = isNaN(this.num(y)) ? 0 : this.num(y);
                return asc ? nx - ny : ny - nx;
            }
            return asc ? x.localeCompare(y) : y.localeCompare(x);
        });

        rows.forEach(r => tbody.appendChild(r));
    }
}">
    <table class="w-full text-sm text-gray-700 dark:text-gray-200">
        <thead>
            <tr class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                @foreach ($head as $i => $col)
                    <th @click="sortBy({{ $i }})"
                        class="py-2 px-2 font-medium text-center cursor-pointer select-none whitespace-nowrap hover:text-gray-600 dark:hover:text-gray-300">
                        {{ $col }}
                        <span class="inline-block w-2 text-primary-500"
                            x-text="dir[{{ $i }}] === true ? '▲' : (dir[{{ $i }}] === false ? '▼' : '')"></span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
