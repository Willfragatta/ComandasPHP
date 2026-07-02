<?php

namespace App\Console\Commands;

use App\Models\ComandaModel;
use App\Services\NetworkFolderMonitor;
use Illuminate\Console\Command;

class MonitorPastaRede extends Command
{
    protected $signature = 'comandas:monitor-pasta-rede';

    protected $description = 'Monitora pasta de rede para arquivos VD*.TXT (polling)';

    public function handle(): int
    {
        $monitor = new NetworkFolderMonitor();

        $monitor->onArquivoRemovido(function (array $data) {
            $this->finalizarComandaPorRemocaoTxt($data['numeroComanda']);
        });

        $monitor->iniciarMonitoramento();
        $this->info('Monitoramento iniciado. Pressione Ctrl+C para parar.');

        while (true) {
            $monitor->tick();
            sleep((int) config('comandas.monitor_intervalo', 5));
        }
    }

    private function finalizarComandaPorRemocaoTxt(string $numeroComanda): void
    {
        $comanda = ComandaModel::buscarAtivaPorNumero($numeroComanda);

        if ($comanda && data_get($comanda, 'status') === 'ATIVA') {
            ComandaModel::atualizar((int) data_get($comanda, 'id'), [
                'status' => 'FINALIZADA',
                'arquivo_txt_criado' => false,
            ]);
            $this->info("Comanda {$numeroComanda} finalizada (TXT removido pelo PDV)");
        }
    }
}
