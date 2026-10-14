<?php

namespace App\Livewire;

use App\Models\SensorData;
use Livewire\Component;

class CowDashboard extends Component
{
    public $latestData = [];

    public $chartData = [];

    // Polling is configured in the blade view using wire:poll
    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        // Puxa os dados mais recentes do banco MongoDB pelo _id (ordem cronológica real de inserção)
        $data = SensorData::orderBy('_id', 'desc')->take(20)->get()->reverse();

        if ($data->isNotEmpty()) {
            $last = $data->last();
            // Extrai o pitch e roll da última amostra (se existir) do último pacote
            $lastPitch = 0;
            $lastRoll = 0;
            $samplesArray = $last->samples ?? [];
            if (is_string($samplesArray)) {
                $samplesArray = json_decode($samplesArray, true);
            }
            if (! empty($samplesArray) && is_array($samplesArray)) {
                $lastSample = end($samplesArray);
                if (is_array($lastSample) && count($lastSample) >= 8) {
                    $lastPitch = $lastSample[6];
                    $lastRoll = $lastSample[7];
                }
            }

            // Se o ESP32 enviou uptime (ex: 489 segundos) em vez de época unix (> 1 bilhão), usa created_at
            $updatedFormatted = ($last->timestamp_unix && $last->timestamp_unix > 1000000000)
                ? date('H:i:s d/m/Y', (int) $last->timestamp_unix)
                : ($last->created_at?->timezone('America/Sao_Paulo')->format('H:i:s d/m/Y') ?? date('H:i:s d/m/Y'));

            $this->latestData = [
                'temperatura' => $last->temperatura_animal ?? 0,
                'passos' => $last->steps ?? 0,
                'ruminacao' => $last->rumination_events ?? 0,
                'atualizacao' => $updatedFormatted,
                'pitch' => (float) $lastPitch,
                'roll' => (float) $lastRoll,
            ];

            // Montar dados para o gráfico
            $labels = [];
            $temperatures = [];
            $steps = [];
            $rumination = [];

            foreach ($data as $d) {
                $labelTime = ($d->timestamp_unix && $d->timestamp_unix > 1000000000)
                    ? date('H:i:s', (int) $d->timestamp_unix)
                    : ($d->created_at?->timezone('America/Sao_Paulo')->format('H:i:s') ?? date('H:i:s'));

                $labels[] = $labelTime;
                $temperatures[] = $d->temperatura_animal;
                $steps[] = $d->steps;
                $rumination[] = $d->rumination_events;
            }

            $this->chartData = [
                'labels' => $labels,
                'temperatures' => $temperatures,
                'steps' => $steps,
                'rumination' => $rumination,
            ];
        }
    }

    public function render()
    {
        return view('livewire.cow-dashboard');
    }
}
