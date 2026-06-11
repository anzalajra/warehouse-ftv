<x-filament-panels::page>
    {{--
        The full LuckPrinter WYSIWYG editor runs inside a same-origin iframe so it
        works 1:1 as designed (Web Bluetooth is allowed in same-origin frames; the
        `allow="bluetooth"` attribute makes that explicit). The editor imports unit
        data from the system via the dataUrl forwarded in $editorUrl.
    --}}
    <div class="-mx-4 -mb-4 sm:-mx-6 sm:-mb-6" style="height: calc(100vh - 10rem); min-height: 560px;">
        <iframe
            src="{{ $editorUrl }}"
            title="LuckPrinter Editor"
            allow="bluetooth"
            class="w-full h-full border-0 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 bg-white"
        ></iframe>
    </div>
</x-filament-panels::page>
