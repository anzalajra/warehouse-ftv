@props(['head' => []])

<div class="overflow-x-auto">
    <table class="w-full text-sm text-gray-700 dark:text-gray-200">
        <thead>
            <tr class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                @foreach ($head as $i => $col)
                    <th @class(['py-2 px-2 font-medium', 'text-right' => $i > 0, 'text-left' => $i === 0])>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
