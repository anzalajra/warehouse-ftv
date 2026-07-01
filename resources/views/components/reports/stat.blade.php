@props(['label' => '', 'value' => ''])

<div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white break-words">{{ $value }}</div>
</div>
