<?php

namespace Bongo\Packages\SocialLogin\Commands;

use System\Console\Command;

/**
 * Diagnostic command to inspect OAuth providers and callback credentials.
 *
 * Usage: php bongo social:check
 */
class SocialCheckCommand extends Command
{
    protected string $name = 'social:check';
    protected string $description = 'Verify OAuth social login provider credentials and redirect URIs';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking OAuth Social Login Providers...");

        $providers = [
            'google' => [
                'name'     => 'Google',
                'id_key'   => 'GOOGLE_CLIENT_ID',
                'sec_key'  => 'GOOGLE_CLIENT_SECRET',
                'red_key'  => 'GOOGLE_REDIRECT_URI',
                'id'       => (string) (env('GOOGLE_CLIENT_ID') ?: config('social.providers.google.client_id')),
                'secret'   => (string) (env('GOOGLE_CLIENT_SECRET') ?: config('social.providers.google.client_secret')),
                'redirect' => (string) (env('GOOGLE_REDIRECT_URI') ?: config('social.providers.google.redirect')),
            ],
            'facebook' => [
                'name'     => 'Facebook',
                'id_key'   => 'FACEBOOK_CLIENT_ID',
                'sec_key'  => 'FACEBOOK_CLIENT_SECRET',
                'red_key'  => 'FACEBOOK_REDIRECT_URI',
                'id'       => (string) (env('FACEBOOK_CLIENT_ID') ?: config('social.providers.facebook.client_id')),
                'secret'   => (string) (env('FACEBOOK_CLIENT_SECRET') ?: config('social.providers.facebook.client_secret')),
                'redirect' => (string) (env('FACEBOOK_REDIRECT_URI') ?: config('social.providers.facebook.redirect')),
            ],
            'github' => [
                'name'     => 'GitHub',
                'id_key'   => 'GITHUB_CLIENT_ID',
                'sec_key'  => 'GITHUB_CLIENT_SECRET',
                'red_key'  => 'GITHUB_REDIRECT_URI',
                'id'       => (string) (env('GITHUB_CLIENT_ID') ?: config('social.providers.github.client_id')),
                'secret'   => (string) (env('GITHUB_CLIENT_SECRET') ?: config('social.providers.github.client_secret')),
                'redirect' => (string) (env('GITHUB_REDIRECT_URI') ?: config('social.providers.github.redirect')),
            ],
        ];

        $anyConfigured = false;

        foreach ($providers as $key => $p) {
            $isConfigured = ($p['id'] !== '' && $p['secret'] !== '');
            if ($isConfigured) {
                $anyConfigured = true;
            }

            $statusMark = $isConfigured ? "\033[32m[READY]\033[0m" : "\033[33m[UNCONFIGURED]\033[0m";
            $this->line("{$p['name']} {$statusMark}");
            $this->line("  Client ID: " . ($p['id'] ? substr($p['id'], 0, 8) . '...' : 'Not set'));
            $this->line("  Redirect:  " . ($p['redirect'] ?: 'Not set'));
            $this->line("");
        }

        if (!$anyConfigured) {
            $this->warn("No OAuth providers configured yet. To enable social login, set provider keys in .env:");
            $this->line("  GOOGLE_CLIENT_ID=");
            $this->line("  GOOGLE_CLIENT_SECRET=");
            $this->line("  GITHUB_CLIENT_ID=");
            $this->line("  GITHUB_CLIENT_SECRET=");
            $this->line("  FACEBOOK_CLIENT_ID=");
            $this->line("  FACEBOOK_CLIENT_SECRET=");
        } else {
            $this->info("Configured providers are ready to handle OAuth redirects and callbacks.");
        }

        return 0;
    }
}
