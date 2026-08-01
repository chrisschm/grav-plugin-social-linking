<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Common\Grav;
use Grav\Plugin\SocialLinking\Provider\MastodonProvider;
use Grav\Plugin\SocialLinking\Provider\ProviderRegistry;
use Grav\Plugin\SocialLinking\Http\SimpleHttpClient;
use Grav\Plugin\SocialLinking\Shortcode\EmbedRenderer;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * Aufrufkonvention (siehe auch README.md):
 *
 *   [social-embed url="https://norden.social/@christiansagt/113..."]
 *   [social-embed service="mastodon" type="status" url="..." refresh="true"]
 *   [social-embed type="profile" url="christiansagt@norden.social"]
 *   [social-embed type="timeline" url="https://norden.social/public/local"]
 *   [social-embed url="..." delete="true"]
 *
 * Benötigt das Plugin "Shortcode Core" (getgrav/grav-plugin-shortcode-core).
 */
class SocialLinkShortcode extends Shortcode
{
    public function init(): void
    {
        $this->shortcode->getHandlers()->add('social-embed', function (ShortcodeInterface $sc) {
            $params = $sc->getParameters();

            // Erlaubt zusätzlich die Kurzschreibweise [social-embed]https://...[/social-embed]
            if (empty($params['url']) && $sc->getBbCode()) {
                $params['url'] = $sc->getBbCode();
            }

            return $this->renderer()->render($params);
        });
    }

    private function renderer(): EmbedRenderer
    {
        $grav = Grav::instance();
        $config = (array) $grav['config']->get('plugins.social-linking');

        $http = new SimpleHttpClient((int) ($config['timeout'] ?? 10));
        $providers = new ProviderRegistry();
        $providers->add(new MastodonProvider($http, (array) ($config['tokens'] ?? [])));

        return new EmbedRenderer($providers, $config);
    }
}
