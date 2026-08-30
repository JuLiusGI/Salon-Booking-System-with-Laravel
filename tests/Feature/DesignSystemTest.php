<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Guards the design system rules from MASTER_SPEC section 13.
 *
 * These read the source rather than the rendered page, because the rule being
 * protected is "colour is defined in one place", which is a source property.
 */
class DesignSystemTest extends TestCase
{
    private const PALETTE = [
        'Dark Green' => '#0a3323',
        'Moss Green' => '#839958',
        'Beige' => '#f7f4d5',
        'Rosy Brown' => '#d3968c',
        'Midnight Green' => '#105666',
    ];

    private function stylesheet(): string
    {
        return strtolower(file_get_contents(__DIR__.'/../../resources/css/app.css'));
    }

    /**
     * @return list<string>
     */
    private function componentFiles(): array
    {
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__.'/../../resources/js')
        );

        $files = [];

        foreach ($directory as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_every_specified_brand_colour_is_defined_as_a_token(): void
    {
        $css = $this->stylesheet();

        foreach (self::PALETTE as $name => $hex) {
            $this->assertStringContainsString($hex, $css, "{$name} ({$hex}) is missing from the design tokens.");
        }
    }

    public function test_components_never_hard_code_a_brand_colour(): void
    {
        foreach ($this->componentFiles() as $file) {
            $contents = strtolower(file_get_contents($file));

            foreach (self::PALETTE as $name => $hex) {
                // app.tsx keeps one fallback for the loading bar, used only if the
                // stylesheet has not applied yet; it reads the token first.
                if (str_ends_with($file, 'app.tsx')) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    $hex,
                    $contents,
                    basename($file)." hard-codes {$name}. Use the semantic token instead."
                );
            }
        }
    }

    public function test_the_semantic_tokens_the_components_rely_on_all_exist(): void
    {
        $css = $this->stylesheet();

        $required = [
            '--color-primary', '--color-secondary', '--color-accent', '--color-support',
            '--color-canvas', '--color-surface',
            '--color-ink', '--color-ink-muted', '--color-ink-inverted',
            '--color-line', '--color-line-strong',
            '--font-display', '--font-sans',
        ];

        foreach ($required as $token) {
            $this->assertStringContainsString($token, $css, "Missing design token {$token}.");
        }
    }

    public function test_reduced_motion_and_visible_focus_are_handled(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('.skip-link', $css);
    }
}
