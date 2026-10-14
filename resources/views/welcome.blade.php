<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento Bovino - Dashboard</title>

    <!-- Tailwind CSS (CDN para prototipagem rÃ¡pida) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- ApexCharts (GrÃ¡ficos) -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Import map para o Three.js r160 (Idêntico ao do Agente CLI) -->
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
                "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
            }
        }
    </script>

    <!-- WebSockets (Pusher & Echo) -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

    @livewireStyles
</head>
<body class="bg-slate-50 min-h-screen">

    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-xl font-bold text-gray-800">CowTrack 🐂</span>
                </div>
                <div class="flex items-center">
                    <span class="text-sm text-green-500 font-medium flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        Recep&ccedil;&atilde;o Ativa
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @livewire('cow-dashboard')
    </main>

    @livewireScripts
</body>
</html>
