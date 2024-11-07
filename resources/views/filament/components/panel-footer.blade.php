<footer class="flex items-center justify-between w-full px-4 py-8 font-medium">
    <span class="text-xs text-center text-gray-400/75 dark:text-gray-600/75">
        <a href="#" class="hover:underline">{{ config('app.name') }}</a> {{
            env('APP_VERSION') ? "v".env('APP_VERSION'): '' }}
    </span>
    <span class="text-xs text-center text-gray-400/75 dark:text-gray-600/75">©{{ date('Y') }} All Rights
        Reserved.
    </span>
</footer>