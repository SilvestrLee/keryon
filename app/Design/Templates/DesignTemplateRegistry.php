<?php

namespace App\Design\Templates;

use App\Design\Templates\Reference\SundayModernReference;
use LogicException;

class DesignTemplateRegistry
{
    /** @var array<string, DesignTemplateDefinition> */
    private array $templates = [];

    /** @param iterable<DesignTemplateDefinition>|null $templates */
    public function __construct(?iterable $templates = null)
    {
        foreach ($templates ?? [SundayModernReference::definition()] as $template) {
            $this->register($template);
        }
    }

    public function register(DesignTemplateDefinition $template): void
    {
        if (isset($this->templates[$template->identity()])) {
            throw new LogicException("Design template [{$template->identity()}] is already registered.");
        }

        foreach ($this->templates as $registered) {
            if (
                $registered->family === $template->family
                && $registered->familyVersion === $template->familyVersion
                && $registered->version !== $template->version
            ) {
                throw new LogicException("Design family [{$template->family}] version [{$template->familyVersion}] must resolve coherently.");
            }
        }

        $this->templates[$template->identity()] = $template;
    }

    public function resolve(string $key, int $version): DesignTemplateDefinition
    {
        return $this->templates["{$key}@{$version}"]
            ?? throw new LogicException("Design template [{$key}@{$version}] is not registered.");
    }

    /** @return list<DesignTemplateDefinition> */
    public function all(): array
    {
        return array_values($this->templates);
    }
}
