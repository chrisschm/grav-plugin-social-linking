<?php

namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * bin/plugin social-linking refresh [pfad]
 *
 * Für die einzelne Bearbeitung eines Beitrags reicht in der Regel der
 * Parameter refresh="true" direkt im [social-embed ...]-Aufruf der
 * jeweiligen Seite. Dieser CLI-Befehl ist für die Wartung mehrerer/aller
 * Seiten auf einmal gedacht, z. B. per Cronjob/Scheduler.
 */
class RefreshCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this
            ->setName('refresh')
            ->setDescription('Löscht zwischengespeicherte Social-Embed-Daten, damit sie beim nächsten Seitenaufruf neu geladen werden')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Seitenordner relativ zu user/pages, z. B. 03.blog/05.mein-beitrag. Ohne Angabe: alle Seiten.'
            )
            ->setHelp(
                'Beispiele:' . PHP_EOL .
                '  bin/plugin social-linking refresh' . PHP_EOL .
                '  bin/plugin social-linking refresh 03.blog/05.mein-beitrag'
            );
    }

    protected function serve(): int
    {
        $grav = Grav::instance();
        $pagesDir = rtrim((string) $grav['locator']->findResource('user://pages'), '/');
        $subfolder = (string) $grav['config']->get('plugins.social-linking.storage_subfolder', '_social-linking');
        $path = $this->input->getArgument('path');
        $searchRoot = $path ? $pagesDir . '/' . trim($path, '/') : $pagesDir;

        if (!is_dir($searchRoot)) {
            $this->output->writeln('<red>Ordner nicht gefunden: ' . $searchRoot . '</red>');
            return 1;
        }

        $count = 0;
        foreach ($this->findCacheFolders($searchRoot, $subfolder) as $dir) {
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                unlink($file);
                $count++;
            }
        }

        $this->output->writeln('<green>' . $count . ' zwischengespeicherte Beiträge werden beim nächsten Aufruf neu geladen.</green>');
        return 0;
    }

    /** @return string[] */
    private function findCacheFolders(string $root, string $subfolder): array
    {
        if (basename($root) === $subfolder) {
            return [$root];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && $item->getFilename() === $subfolder) {
                $found[] = $item->getPathname();
            }
        }
        return $found;
    }
}
