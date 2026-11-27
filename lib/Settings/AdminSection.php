<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Settings;

use OCA\FlzPermissionMatrix\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IL10N;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
    public function __construct(
        private IURLGenerator $url,
        private ?IL10N $l10n = null,
    ) {
    }

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }

    public function getIcon(): string {
        return $this->url->imagePath(Application::APP_ID, 'app.svg');
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return $this->t('Berechtigungsmatrix');
    }

    public function getPriority(): int {
        return 61;
    }
}
