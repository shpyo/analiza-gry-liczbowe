<?php
declare(strict_types=1);

final class Router
{
    public const PAGE_URL_SLUG = [
        'dashboard'    => '',
        'draws'        => 'losowania',
        'stats'        => 'statystyki',
        'generator'    => 'generator',
        'validator'    => 'weryfikator',
        'cooccurrence' => 'wspolwystepowanie',
    ];

    /** @var array<string,string> URL slug → internal page */
    private readonly array $urlSlugToPage;

    /** @param list<string> $allowedGameSlugs DB slugs (with underscores) */
    public function __construct(private readonly array $allowedGameSlugs)
    {
        $map = [];
        foreach (self::PAGE_URL_SLUG as $page => $slug) {
            if ($slug !== '') {
                $map[$slug] = $page;
            }
        }
        $this->urlSlugToPage = $map;
    }

    /**
     * Parse REQUEST_URI into a route descriptor.
     *
     * @return array{page:string,gameSlug:?string,notFound:bool}
     *   page='home' for `/` (caller redirects to default game dashboard).
     */
    public function parse(string $requestUri): array
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return ['page' => 'home', 'gameSlug' => null, 'notFound' => false];
        }

        $segments = explode('/', trim($path, '/'));

        // /sync
        if ($segments[0] === 'sync' && count($segments) === 1) {
            return ['page' => 'sync', 'gameSlug' => null, 'notFound' => false];
        }

        // /import  |  /import/{game}
        if ($segments[0] === 'import') {
            if (count($segments) === 1) {
                return ['page' => 'import', 'gameSlug' => null, 'notFound' => false];
            }
            if (count($segments) === 2) {
                $dbSlug = $this->gameUrlToDb($segments[1]);
                if (!in_array($dbSlug, $this->allowedGameSlugs, true)) {
                    return ['page' => 'import', 'gameSlug' => null, 'notFound' => true];
                }
                return ['page' => 'import', 'gameSlug' => $dbSlug, 'notFound' => false];
            }
        }

        // /gra/{game}[/{strona}]
        if ($segments[0] === 'gra' && count($segments) >= 2 && count($segments) <= 3) {
            $dbSlug = $this->gameUrlToDb($segments[1]);
            if (!in_array($dbSlug, $this->allowedGameSlugs, true)) {
                return ['page' => 'dashboard', 'gameSlug' => null, 'notFound' => true];
            }
            if (count($segments) === 2) {
                return ['page' => 'dashboard', 'gameSlug' => $dbSlug, 'notFound' => false];
            }
            $pageSlug = $segments[2];
            if (!isset($this->urlSlugToPage[$pageSlug])) {
                return ['page' => 'dashboard', 'gameSlug' => $dbSlug, 'notFound' => true];
            }
            return ['page' => $this->urlSlugToPage[$pageSlug], 'gameSlug' => $dbSlug, 'notFound' => false];
        }

        return ['page' => 'dashboard', 'gameSlug' => null, 'notFound' => true];
    }

    /**
     * Build a pretty URL.
     *
     * @param array<string,mixed> $query query-string params (appended as-is)
     */
    public function url(string $page, ?string $gameDbSlug = null, array $query = []): string
    {
        $path = match (true) {
            $page === 'sync'   => '/sync',
            $page === 'import' => $gameDbSlug ? '/import/' . $this->gameDbToUrl($gameDbSlug) : '/import',
            default            => $this->gamePath($page, $gameDbSlug),
        };

        $filtered = array_filter($query, static fn($v) => $v !== null && $v !== '');
        return $filtered === [] ? $path : $path . '?' . http_build_query($filtered);
    }

    private function gamePath(string $page, ?string $gameDbSlug): string
    {
        if (!isset(self::PAGE_URL_SLUG[$page])) {
            return '/';
        }
        $game = $gameDbSlug ? $this->gameDbToUrl($gameDbSlug) : 'lotto';
        $pageSlug = self::PAGE_URL_SLUG[$page];
        return $pageSlug === ''
            ? '/gra/' . $game
            : '/gra/' . $game . '/' . $pageSlug;
    }

    private function gameDbToUrl(string $dbSlug): string
    {
        return str_replace('_', '-', $dbSlug);
    }

    private function gameUrlToDb(string $urlSlug): string
    {
        return str_replace('-', '_', $urlSlug);
    }
}
