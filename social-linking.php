<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Plugin\SocialLinking\Http\SimpleHttpClient;
use Grav\Plugin\SocialLinking\Provider\MastodonProvider;
use Grav\Plugin\SocialLinking\Provider\ProviderRegistry;
use Grav\Plugin\SocialLinking\Shortcode\EmbedRenderer;

/**
 * Social Linking Plugin.
 *
 * Bindet Beiträge (und später Profile/Timelines) von Mastodon und
 * kompatiblen Diensten über den [social-embed]-Shortcode bzw. die
 * social_embed()-Twig-Funktion ein. Details zur Aufrufkonvention siehe
 * README.md und shortcodes/SocialEmbedShortcode.php.
 */
class SocialLinkingPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        $this->registerAutoloader();

        $this->enable([
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigInitialized'   => ['onTwigInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onAssetsInitialized' => ['onAssetsInitialized', 0],
        ]);
    }

    /**
     * Registriert den [social-embed]-Shortcode bei Shortcode Core.
     * Die eigentliche Klasse liegt in shortcodes/SocialEmbedShortcode.php,
     * die Konvention des Shortcode-Core-Plugins verlangt genau diesen Ordner.
     */
    public function onShortcodeHandlers(): void
    {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__ . '/shortcodes');
    }

    /**
     * Twig-Fallback für die direkte Nutzung in Templates, z. B.:
     *
     *   {{ social_embed({url: 'https://norden.social/@user/12345'}) }}
     *
     * Nützlich für Theme-Entwickler, die Embeds unabhängig vom
     * Markdown-Shortcode-Mechanismus einsetzen möchten - dieselbe
     * Aufrufkonvention gilt für beide Wege.
     */
    public function onTwigInitialized(): void
    {
        $twig = $this->grav['twig']->twig();
        $twig->addFunction(new \Twig\TwigFunction(
            'social_embed',
            fn (array $params = []) => $this->renderer()->render($params),
            ['is_safe' => ['html']]
        ));
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onAssetsInitialized(): void
    {
        $this->grav['assets']->addCss('plugin://social-linking/css/social-linking.css');
    }

    private function renderer(): EmbedRenderer
    {
        $config = (array) $this->config->get('plugins.social-linking');

        $http = new SimpleHttpClient(
            (int) ($config['timeout'] ?? 10),
            'Grav-SocialLinking/1.0 (+https://github.com/)',
            (array) ($config['allowed_private_hosts'] ?? [])
        );
        $providers = new ProviderRegistry();
        $providers->add(new MastodonProvider($http, (array) ($config['tokens'] ?? [])));

        return new EmbedRenderer($providers, $config);
    }

    private function registerAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Grav\\Plugin\\SocialLinking\\';
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = __DIR__ . '/classes/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
