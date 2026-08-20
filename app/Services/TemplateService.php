<?php

namespace App\Services;

use App\Models\BotLocale;
use App\Models\BotResource;
use Illuminate\Support\Facades\Cache;

class TemplateService
{
    /**
     * Get template by key and locale
     */
    public function getTemplate(string $key, ?int $localeId = null): ?string
    {
        $localeId = $localeId ?? $this->getDefaultLocaleId();

        // Cache templates for 1 hour
        $cacheKey = "template.{$key}.{$localeId}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $localeId) {
            return BotResource::getByKey($key, $localeId);
        });
    }

    /**
     * Get template with variable substitution
     */
    public function getTemplateWithVars(string $key, array $vars = [], ?int $localeId = null): ?string
    {
        $template = $this->getTemplate($key, $localeId);

        if (! $template) {
            return null;
        }

        // Replace variables like {username}, {ticket_id}, etc.
        foreach ($vars as $varKey => $value) {
            $template = str_replace("{{$varKey}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get default locale ID
     */
    public function getDefaultLocaleId(): int
    {
        return Cache::remember('default_locale_id', 3600, function () {
            return BotLocale::getDefault()?->id ?? 1;
        });
    }

    /**
     * Get locale by code
     */
    public function getLocaleByCode(string $code): ?BotLocale
    {
        return Cache::remember("locale.{$code}", 3600, function () use ($code) {
            return BotLocale::where('code', $code)->first();
        });
    }

    /**
     * Get close keywords for a locale.
     * Reads config.close_keywords JSON from bot_resources and returns
     * a lowercase array ready for string matching.
     */
    public function getCloseKeywords(int $localeId): array
    {
        $json = $this->getTemplate('config.close_keywords', $localeId);

        if (! $json) {
            return [];
        }

        $keywords = json_decode($json, true);

        if (! is_array($keywords)) {
            return [];
        }

        return array_map('mb_strtolower', $keywords);
    }
}
