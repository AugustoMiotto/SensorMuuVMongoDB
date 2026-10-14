<div wire:poll.2s="loadData" class="p-6">
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Painel de Monitoramento Bovino</h1>
            <p class="text-gray-500">Última atualização: {{ $latestData['atualizacao'] ?? 'Aguardando ESP32...' }}</p>
        </div>
        <div class="text-right">
            <span class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold mb-2">
                @if(($latestData['ruminacao'] ?? 0) > 0)
                    🐂 Ruminando
                @elseif(($latestData['passos'] ?? 0) > 0)
                    🚶 Caminhando
                @else
                    💤 Parada
                @endif
            </span>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-gray-500 text-sm font-medium mb-1">Temperatura</h3>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold text-orange-500">{{ number_format($latestData['temperatura'] ?? 0, 1) }}</span>
                <span class="text-gray-500 font-medium mb-1">°C</span>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-gray-500 text-sm font-medium mb-1">Passos</h3>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold text-blue-500">{{ $latestData['passos'] ?? 0 }}</span>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-gray-500 text-sm font-medium mb-1">Ruminação</h3>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold text-green-500">{{ $latestData['ruminacao'] ?? 0 }}</span>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-gray-500 text-sm font-medium mb-1">Inclinação (Pitch)</h3>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold text-purple-500">{{ number_format($latestData['pitch'] ?? 0, 0) }}°</span>
            </div>
        </div>
    </div>

    <!-- Simulador 3D e Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="cowCharts">
        
        <!-- Simulador 3D -->
        <div class="col-span-1 transition-all duration-300" :class="fullscreen ? 'fixed inset-4 z-50' : 'relative'" wire:ignore>
            <div class="bg-gray-900 rounded-xl shadow-2xl overflow-hidden relative w-full h-full border border-gray-700" :style="fullscreen ? 'height: 100%' : 'height: 350px;'">
                
                <!-- Overlay Header -->
                <div class="absolute top-4 left-4 right-4 z-10 flex justify-between items-start pointer-events-none">
                    <div>
                        <h3 class="text-sm font-bold text-white mb-1 drop-shadow-md">Simulador 3D (Ao Vivo)</h3>
                        <p class="text-xs text-gray-300 drop-shadow-md font-mono">
                            Pitch: <span id="lbl-pitch" class="text-purple-400">0</span>° | 
                            Roll: <span id="lbl-roll" class="text-blue-400">0</span>°
                        </p>
                    </div>
                    
                    <button @click="toggleFullscreen()" class="pointer-events-auto bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white p-2 rounded-lg transition-colors">
                        <svg x-show="!fullscreen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                        <svg x-show="fullscreen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div id="sim-container" class="w-full h-full cursor-grab active:cursor-grabbing"></div>
            </div>
            
            <!-- Backdrop escuro para quando estiver em tela cheia -->
            <div x-show="fullscreen" x-cloak class="fixed inset-0 bg-gray-900/80 -z-10 backdrop-blur-sm"></div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 col-span-1" wire:ignore>
            <h3 class="text-sm font-bold text-gray-700 mb-2">Temperatura ao longo do tempo</h3>
            <div id="chart-temp"></div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 col-span-1" wire:ignore>
            <h3 class="text-sm font-bold text-gray-700 mb-2">Atividade (Passos e Ruminação)</h3>
            <div id="chart-activity"></div>
        </div>


    @script
    <script>
        // Declarando instâncias do Three.js FORA do Alpine.data()
        let simScene, simCamera, simRenderer, simCowNeck, simCowHead;
        let simMixer, simClock;
        let simActions = {};
        let simCurrentAction = null;
        let simControls;
        
        // Variáveis de suavização separadas para não brigar com o AnimationMixer
        let smoothedPitch = 0;
        let smoothedRoll = 0;

        Alpine.data('cowCharts', () => ({
            chartTemp: null,
            chartActivity: null,
            fullscreen: false,

            toggleFullscreen() {
                this.fullscreen = !this.fullscreen;
                // Dispara evento de resize após a transição do DOM
                setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 50);
                setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 300);
            },

            init() {
                // Initialize Charts
                let data = $wire.chartData;
                
                var optionsTemp = {
                    series: [{ name: "Temperatura °C", data: data?.temperatures || [] }],
                    chart: { height: 280, type: 'line', animations: { enabled: false }, toolbar: {show: false} },
                    stroke: { curve: 'smooth', colors: ['#f97316'], width: 3 },
                    xaxis: { categories: data?.labels || [], labels: {show: false} }
                };

                var optionsActivity = {
                    series: [
                        { name: 'Passos', data: data?.steps || [] }, 
                        { name: 'Ruminação', data: data?.rumination || [] }
                    ],
                    chart: { type: 'bar', height: 280, animations: { enabled: false }, toolbar: {show: false}, stacked: true },
                    xaxis: { categories: data?.labels || [], labels: {show: false} },
                    fill: { opacity: 1, colors: ['#3b82f6', '#22c55e'] }
                };

                this.chartTemp = new ApexCharts(document.getElementById("chart-temp"), optionsTemp);
                this.chartActivity = new ApexCharts(document.getElementById("chart-activity"), optionsActivity);
                
                this.chartTemp.render();
                this.chartActivity.render();

                // Initialize 3D Simulator
                this.initSimulator();

                // Livewire 3 observer (para gráficos a cada 10s)
                $wire.$watch('chartData', (newData) => {
                    if(!newData || !newData.labels) return;
                    
                    this.chartTemp.updateSeries([{ data: newData.temperatures }]);
                    this.chartTemp.updateOptions({ xaxis: { categories: newData.labels } });

                    this.chartActivity.updateSeries([
                        { data: newData.steps }, 
                        { data: newData.rumination }
                    ]);
                    this.chartActivity.updateOptions({ xaxis: { categories: newData.labels } });
                });

                // WebSocket / Reverb (Para o simulador 3D em tempo real)
                console.log("Iniciando conexão WebSocket com Reverb...");
                
                if (window.Pusher) {
                    window.Pusher = window.Pusher || Pusher;
                }

                window.Echo = new Echo({
                    broadcaster: 'reverb',
                    key: '{{ env('REVERB_APP_KEY') }}',
                    wsHost: window.location.hostname,
                    wsPort: {{ env('REVERB_PORT', 8080) }},
                    wssPort: {{ env('REVERB_PORT', 8080) }},
                    forceTLS: (window.location.protocol === 'https:'),
                    enabledTransports: ['ws', 'wss'],
                });

                if (typeof window.Echo.socketId !== 'function') {
                    window.Echo.socketId = function() {
                        return window.Echo.connector?.pusher?.connection?.socket_id || '';
                    };
                }

                window.Echo.connector.pusher.connection.bind('connected', function() {
                    console.log("✅ Conectado ao Reverb com sucesso!");
                });

                window.Echo.channel('cow.fbd9bb9c-e526-4f9b-a1d7-3dfe21400990')
                    .listen('.sensor.update', (data) => {
                        window.lastWsTime = Date.now();
                        window.realtimePitch = data.pitch;
                        window.realtimeRoll = data.roll;
                        
                        // Liga imediatamente, mas demora 2s para desligar (debounce)
                        if (data.is_moving) {
                            window.realtimeMoving = true;
                            if (window.moveTimeout) {
                                clearTimeout(window.moveTimeout);
                                window.moveTimeout = null;
                            }
                        } else if (window.realtimeMoving && !window.moveTimeout) {
                            window.moveTimeout = setTimeout(() => {
                                window.realtimeMoving = false;
                                window.moveTimeout = null;
                            }, 2000); // 2 segundos de inércia
                        }

                        if (data.is_ruminating) {
                            window.realtimeRuminating = true;
                            if (window.rumiTimeout) {
                                clearTimeout(window.rumiTimeout);
                                window.rumiTimeout = null;
                            }
                        } else if (window.realtimeRuminating && !window.rumiTimeout) {
                            window.rumiTimeout = setTimeout(() => {
                                window.realtimeRuminating = false;
                                window.rumiTimeout = null;
                            }, 2000);
                        }
                    });
            },

            async initSimulator() {
                const container = document.getElementById('sim-container');
                
                // Imports dinâmicos do ES Modules (Mesma versão do CLI Agent: r160)
                const THREE = await import('three');
                const { GLTFLoader } = await import('three/addons/loaders/GLTFLoader.js');
                const { OrbitControls } = await import('three/addons/controls/OrbitControls.js');

                simScene = new THREE.Scene();
                simScene.background = new THREE.Color(0x87CEEB); 
                simScene.fog = new THREE.Fog(0x87CEEB, 10, 50);

                simClock = new THREE.Clock();
                
                simCamera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.25, 100);
                simCamera.position.set(2.5, 1.8, 3.5);
                simCamera.lookAt(0, 0.6, 0);

                simRenderer = new THREE.WebGLRenderer({ antialias: true });
                simRenderer.setSize(container.clientWidth, container.clientHeight);
                simRenderer.setPixelRatio(window.devicePixelRatio);
                
                simRenderer.shadowMap.enabled = true;
                simRenderer.toneMapping = THREE.ACESFilmicToneMapping;
                simRenderer.toneMappingExposure = 1.0;
                
                container.appendChild(simRenderer.domElement);

                simControls = new OrbitControls(simCamera, simRenderer.domElement);
                simControls.enableDamping = true;
                simControls.dampingFactor = 0.05;
                simControls.target.set(0, 0.6, 0);
                simControls.maxPolarAngle = Math.PI / 2 + 0.1;

                const dirLight = new THREE.DirectionalLight(0xffffff, 1.5); 
                dirLight.position.set(3, 10, 10);
                dirLight.castShadow = true;
                simScene.add(dirLight);
                
                const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 1.5);
                hemiLight.position.set(0, 20, 0);
                simScene.add(hemiLight);

                const canvas = document.createElement('canvas');
                canvas.width = 256;
                canvas.height = 256;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#416033'; 
                ctx.fillRect(0, 0, 256, 256);
                for(let i = 0; i < 3000; i++) {
                    ctx.fillStyle = Math.random() > 0.5 ? '#36522a' : '#537a41';
                    ctx.fillRect(Math.random() * 256, Math.random() * 256, 2, Math.random() * 8 + 4);
                }
                const grassTex = new THREE.CanvasTexture(canvas);
                grassTex.wrapS = THREE.RepeatWrapping;
                grassTex.wrapT = THREE.RepeatWrapping;
                grassTex.repeat.set(40, 40);
                // No r160, texturas de cor devem estar no ColorSpace correto
                grassTex.colorSpace = THREE.SRGBColorSpace;

                const groundGeo = new THREE.PlaneGeometry(100, 100);
                const groundMat = new THREE.MeshStandardMaterial({ 
                    map: grassTex, 
                    roughness: 1.0, 
                    metalness: 0.0 
                });
                
                const ground = new THREE.Mesh(groundGeo, groundMat);
                ground.rotation.x = -Math.PI / 2;
                ground.receiveShadow = true;
                simScene.add(ground);

                simCowNeck = null;

                const loader = new GLTFLoader();
                loader.load('/models/vaca_dashboard.glb', (gltf) => {
                    const model = gltf.scene;
                    
                    model.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = true;
                            child.receiveShadow = true;
                        }
                        // O outro agente usou nome exato 'Bone_Neck'. Vamos manter compatibilidade máxima.
                        if (child.isBone && child.name === 'Bone_Neck') {
                            simCowNeck = child;
                            console.log("✅ Osso 'Bone_Neck' configurado com sucesso para o ESP32!");
                        }
                    });
                    
                    simScene.add(model);
                    
                    if (gltf.animations && gltf.animations.length > 0) {
                        simMixer = new THREE.AnimationMixer(model);
                        gltf.animations.forEach((clip) => {
                            simActions[clip.name] = simMixer.clipAction(clip);
                        });

                        let idleName = 'Idle' in simActions ? 'Idle' : (Object.keys(simActions).find(n => n.includes('Idle')) || gltf.animations[0].name);

                        if (simActions[idleName]) {
                            simActions[idleName].play();
                            simCurrentAction = idleName;
                        }
                    }

                    if (!simCowNeck) {
                        console.warn("⚠️ Nenhum osso de pescoço encontrado. Inclinando o modelo inteiro.");
                        simCowNeck = model;
                    }
                });

                const animate = () => {
                    requestAnimationFrame(animate);
                    
                    let delta = simClock.getDelta();

                    if (simControls) simControls.update();

                    if (simMixer) {
                        simMixer.update(delta);

                        // Máquina de estados INSTANTÂNEA baseada no websocket
                        let isMoving = window.realtimeMoving || false;
                        let isRuminating = window.realtimeRuminating || false;
                        
                        let idleName = 'Idle' in simActions ? 'Idle' : (Object.keys(simActions).find(n => n.includes('Idle')) || Object.keys(simActions)[0]);
                        let chewName = 'Chew' in simActions ? 'Chew' : (Object.keys(simActions).find(n => n.includes('Chew')) || idleName);
                        let walkName = 'Walk' in simActions ? 'Walk' : (Object.keys(simActions).find(n => n.includes('Walk')) || idleName);

                        let nextAction = idleName;
                        if (isRuminating) nextAction = chewName;
                        else if (isMoving) nextAction = walkName;

                        if (simActions[nextAction] && simCurrentAction !== nextAction) {
                            if (simCurrentAction && simActions[simCurrentAction]) {
                                simActions[simCurrentAction].fadeOut(0.3); // Transição mais rápida (300ms)
                            }
                            simActions[nextAction].reset().fadeIn(0.3).play();
                            simCurrentAction = nextAction;
                        }
                    }

                    if(simCowNeck) {
                        let lwData = $wire.get('latestData') || {};
                        let isWsRecent = window.lastWsTime && (Date.now() - window.lastWsTime < 4000);
                        let p = (isWsRecent && window.realtimePitch !== undefined) ? window.realtimePitch : (lwData.pitch ?? 0);
                        let r = (isWsRecent && window.realtimeRoll !== undefined) ? window.realtimeRoll : (lwData.roll ?? 0);

                        p = parseFloat(p) || 0.0;
                        r = parseFloat(r) || 0.0;

                        document.getElementById('lbl-pitch').innerText = Math.round(p);
                        document.getElementById('lbl-roll').innerText = Math.round(r);

                        let targetPitch = THREE.MathUtils.degToRad(p);
                        let targetRoll = THREE.MathUtils.degToRad(r);

                        smoothedPitch += (targetPitch - smoothedPitch) * 0.15;
                        smoothedRoll += (targetRoll - smoothedRoll) * 0.15;

                        simCowNeck.rotation.x = smoothedPitch;
                        simCowNeck.rotation.y = 0;
                        simCowNeck.rotation.z = smoothedRoll;

                        simCowNeck.updateMatrix();
                        simCowNeck.updateMatrixWorld(true);
                    }

                    simRenderer.render(simScene, simCamera);
                };
                animate();
                
                window.addEventListener('resize', () => {
                    if(!container) return;
                    simCamera.aspect = container.clientWidth / container.clientHeight;
                    simCamera.updateProjectionMatrix();
                    simRenderer.setSize(container.clientWidth, container.clientHeight);
                });
            }
        }));
    </script>
    @endscript
</div>
