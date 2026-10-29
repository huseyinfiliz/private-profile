<?php

namespace huseyinfiliz\PrivateProfile;

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;

class ProfileTabRegistry
{
    public const SETTING = 'huseyinfiliz-private-profile.discovered_tabs';

    public function __construct(protected SettingsRepositoryInterface $settings, protected ExtensionManager $extensions)
    {
    }

    public static function automaticId(string $path): string
    {
        return 'auto_'.rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }

    public function tabs(): array
    {
        $tabs = [];
        foreach (VisibilityResolver::TABS as $id => [$path, $extension]) {
            if (! $extension || $this->extensions->isEnabled($extension)) {
                $tabs[$path] = [
                    'id' => $id,
                    'path' => $path,
                    // Byobu currently has a page guard, but no API adapter.
                    'apiProtected' => $id !== 'privateDiscussions',
                ];
            }
        }

        $discovered = json_decode((string) $this->settings->get(self::SETTING, '[]'), true);
        foreach (is_array($discovered) ? $discovered : [] as $entry) {
            if (! is_array($entry) || ! $this->validPath($entry['path'] ?? null)) {
                continue;
            }
            $path = $entry['path'];
            if (! isset($tabs[$path])) {
                $tabs[$path] = ['id' => self::automaticId($path), 'path' => $path, 'apiProtected' => false];
            }
            if (is_string($entry['label'] ?? null)) {
                $tabs[$path]['label'] = mb_substr($entry['label'], 0, 120);
            }
        }

        return array_values($tabs);
    }

    public function discover(mixed $entries): array
    {
        if (! is_array($entries) || count($entries) > 100) {
            throw new ValidationException(['tabs' => 'Expected at most 100 profile tabs.']);
        }

        $tabs = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! $this->validPath($entry['path'] ?? null)
                || ! is_string($entry['label'] ?? null) || mb_strlen($entry['label']) > 120) {
                throw new ValidationException(['tabs' => 'Invalid profile tab path or label.']);
            }
            // IDs and API coverage are determined here, never trusted from the browser.
            $tabs[$entry['path']] = ['path' => $entry['path'], 'label' => trim($entry['label'])];
        }

        $this->settings->set(self::SETTING, json_encode(array_values($tabs), JSON_THROW_ON_ERROR));

        return $this->tabs();
    }

    private function validPath(mixed $path): bool
    {
        return is_string($path) && strlen($path) <= 128
            && ! preg_match('~^/security(?:/|$)~', $path)
            && ($path === '' || (bool) preg_match('~^/(?:[\p{L}\p{N}_:.\-]+/?)*$~uD', $path));
    }
}
