<?php

namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * bin/plugin social-linking purge [pfad] [--yes]
 *
 * Für das gezielte Löschen eines einzelnen Beitrags reicht in der Regel der
 * Parameter delete="true" direkt im [social-embed ...]-Aufruf. Dieser
 * CLI-Befehl entfernt komplette Cache-Ordner (JSON + lokale Medien), z. B.
 * um verwaiste Daten nach dem Entfernen von Shortcodes aus dem Seiteninhalt
 * aufzuräumen.
 */
class PurgeCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this
            ->setName('purge')
            ->setDescription('Entfernt zwischengespeicherte Social-Embed-Daten inkl. lokaler Medien vollständig')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Seitenordner relativ zu user/pages, z. B. 03.blog/05.mein-beitrag. Ohne Angabe: alle Seiten.'
            )
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Sicherheitsabfrage überspringen')
            ->setHelp(
                'Beispiele:' . PHP_EOL .
                '  bin/plugin social-linking purge --yes' . PHP_EOL .
                '  bin/plugin social-linking purge 03.blog/05.mein-beitrag'
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

        $folders = $this->findCacheFolders($searchRoot, $subfolder);
        if (empty($folders)) {
            $this->output->writeln('Keine Social-Embed-Caches gefunden.');
            return 0;
        }

        if (!$this->input->getOption('yes')) {
            $question = new ConfirmationQuestion(
                sprintf('%d Cache-Ordner werden unwiderruflich gelöscht. Fortfahren? [y/N] ', count($folders)),
                false
            );
            if (!$this->getHelper('question')->ask($this->input, $this->output, $question)) {
                $this->output->writeln('Abgebrochen.');
                return 0;
            }
        }

        foreach ($folders as $dir) {
            $this->rrmdir($dir);
        }

        $this->output->writeln('<green>' . count($folders) . ' Cache-Ordner gelöscht.</green>');
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

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
