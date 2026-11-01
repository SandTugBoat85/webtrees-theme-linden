<?php

/**
 * Linden: a clean, banner-led theme for webtrees. The banner, icon, accent colour and
 * home-screen name are set in the control panel (Modules > Themes > Linden).
 *
 * Copyright (C) 2026 Kevin Wiblin. Licensed under the GNU General Public License,
 * version 3 or later: see LICENSE.md.
 */

declare(strict_types=1);

namespace Linden\WebtreesTheme;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\MinimalTheme;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use GdImage;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class LindenTheme extends MinimalTheme implements ModuleConfigInterface, ModuleCustomInterface, ModuleGlobalInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;
    use ModuleGlobalTrait;

    // Google Fonts' subsets for Source Sans 3, so each file only loads when a page needs it
    private const FONT_RANGES = [
        'latin'     => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C4, U+2113, U+2C60-2C7F, U+A720-A7FF',
    ];

    public const DEFAULT_ACCENT = '#3f6150';

    // Accent colours offered on the settings page. All give white text at least 4.5:1 contrast.
    public const PRESETS = [
        'Linden green' => '#3f6150',
        'Charcoal'     => '#3a3b3d',
        'Navy'         => '#2b4162',
        'Burgundy'     => '#6d2e46',
        'Teal'         => '#2f6f73',
        'Rust'         => '#8a4b2a',
    ];

    // Uploaded images are kept in webtrees' data folder, which the web server does not serve
    private const DATA_FOLDER = 'linden-theme/';

    private const BANNER_MAX_WIDTH = 2400;
    private const ICON_SIZES       = [32, 180, 192, 512];
    private const ICON_MIN_SIZE    = 192;

    // Refuse images that would take too much memory to decode: GD needs 4 bytes a pixel, and
    // PHP's memory_limit is often 128MB
    private const MAX_PIXELS = 16_000_000;

    // Which part of the banner stays in view when a phone crops it: the setting and its position
    public const BANNER_FOCUS = ['0' => 'Left', '25' => 'Left of centre', '50' => 'Centre', '75' => 'Right of centre', '100' => 'Right'];

    // Longest home-screen name, short name and description
    private const MAX_LENGTHS = ['app-name' => 100, 'app-short-name' => 30, 'app-description' => 200];

    public function title(): string
    {
        return 'Linden';
    }

    public function description(): string
    {
        return 'A clean theme with your own banner, icon and colour.';
    }

    public function customModuleAuthorName(): string
    {
        return 'Kevin Wiblin';
    }

    public function customModuleVersion(): string
    {
        return '1.2.0';
    }

    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/SandTugBoat85/webtrees-theme-linden';
    }

    /**
     * webtrees reads the latest version from here once a day, and the control panel says when
     * there is a newer one. The file on main always names the latest release.
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/SandTugBoat85/webtrees-theme-linden/main/latest-version.txt';
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
        View::registerCustomView('::webmanifest-json', $this->name() . '::webmanifest-json');
    }

    public function bootstrapColorScheme(): string
    {
        return 'light';
    }

    /**
     * The theme menu, unless the settings keep it for administrators.
     */
    public function menuThemes(): Menu|null
    {
        return $this->getPreference('theme-menu', '1') === '1' || Auth::isAdmin() ? parent::menuThemes() : null;
    }

    public function stylesheets(): array
    {
        $stylesheets = [...parent::stylesheets(), $this->assetUrl('css/linden.css')];

        if ($this->banner() !== null) {
            $stylesheets[] = $this->assetUrl($this->getPreference('banner-title') === '1' ? 'css/banner-title.css' : 'css/banner.css');
        }

        if ($this->getPreference('chart-ages') !== '1') {
            $stylesheets[] = $this->assetUrl('css/hide-chart-ages.css');
        }

        return $stylesheets;
    }

    /**
     * Module assets are served through a query-string route, so relative url()s in the
     * stylesheet cannot reach them. Declare the fonts, colours and banner here instead.
     */
    public function headContent(): string
    {
        if (!$this->inUse()) {
            return '';
        }

        $css = '';

        foreach (['normal', 'italic'] as $style) {
            foreach (self::FONT_RANGES as $subset => $range) {
                $url = $this->assetUrl('fonts/source-sans-3-' . $style . '-' . $subset . '.woff2');
                $css .= "@font-face{font-family:'Source Sans 3';font-style:{$style};font-weight:200 900;font-display:swap;" .
                    'src:' . self::cssUrl($url) . " format('woff2');unicode-range:{$range}}\n";
            }
        }

        $accent = $this->accent();
        [$red, $green, $blue] = sscanf($accent, '#%02x%02x%02x');
        $on_accent = self::luminance($red, $green, $blue) > 0.179 ? '#222222' : '#ffffff';

        // Links in the accent colour must stay readable on white: darken a pale accent until it
        // reaches 4.5:1 contrast (WCAG AA), which needs a luminance of 0.183 or less
        [$link_red, $link_green, $link_blue] = [$red, $green, $blue];

        while (self::luminance($link_red, $link_green, $link_blue) > 0.183) {
            [$link_red, $link_green, $link_blue] = array_map(static fn (int $channel): int => (int) ($channel * 0.95), [$link_red, $link_green, $link_blue]);
        }

        $link = sprintf('#%02x%02x%02x', $link_red, $link_green, $link_blue);

        $css .= ":root{--linden-accent:{$accent};--linden-accent-rgb:{$red}, {$green}, {$blue};--linden-on-accent:{$on_accent};" .
            "--linden-link:{$link};--linden-link-rgb:{$link_red}, {$link_green}, {$link_blue}}\n";

        $banner = $this->banner();

        if ($banner !== null) {
            $css .= ':root{--linden-banner:' . self::cssUrl($banner['url']) . ";--linden-banner-ratio:{$banner['width']} / {$banner['height']};" .
                '--linden-banner-focus:' . $this->bannerFocus() . "%}\n";
        }

        // The layout has already listed webtrees' icons; browsers use the last icon given
        // for each size, so these take over.
        $head = '<meta name="theme-color" content="' . e($accent) . '">';

        if ($this->hasIcon()) {
            $head .=
                '<link rel="icon" sizes="32x32" href="' . e($this->iconUrl(32)) . '">' .
                '<link rel="icon" sizes="192x192" href="' . e($this->iconUrl(192)) . '">' .
                '<link rel="apple-touch-icon" sizes="180x180" href="' . e($this->iconUrl(180)) . '">';
        }

        return '<style>' . $css . '</style>' . $head;
    }

    /**
     * "Theme: Linden" in the footer, unless the settings turn it off. It is added here rather than
     * as a footer module, because unticking a footer module in the control panel disables the
     * whole module, theme and all. The layout puts this after the footer, so a line of script
     * moves it into the footer band; without script it stays just below.
     */
    public function bodyContent(): string
    {
        if (!$this->inUse() || $this->getPreference('footer-credit', '1') !== '1') {
            return '';
        }

        return '<div class="wt-footer linden-credit">' . I18N::translate('Theme') . ': ' .
            '<a href="' . e($this->customModuleSupportUrl()) . '">Linden</a></div>' .
            '<script>document.querySelector(".wt-footers")?.append(document.querySelector(".linden-credit"))</script>';
    }

    /**
     * The web manifest, for "Add to home screen".
     *
     * @return array<string,mixed>
     */
    public function manifest(string $base_url): array
    {
        $icons = [];

        foreach ($this->hasIcon() ? [192, 512] : [] as $size) {
            $icons[] = (object) ['src' => $this->iconUrl($size), 'sizes' => $size . 'x' . $size, 'type' => 'image/png'];
        }

        $name = $this->getPreference('app-name') ?: 'webtrees';

        return [
            'name'             => $name,
            'short_name'       => $this->getPreference('app-short-name') ?: $name,
            'description'      => $this->getPreference('app-description') ?: 'webtrees online genealogy',
            'scope'            => (parse_url($base_url, PHP_URL_PATH) ?? '') . '/',
            'start_url'        => $base_url . '/',
            'background_color' => '#ffffff',
            'theme_color'      => $this->accent(),
            'display'          => 'standalone',
            'icons'            => $icons ?: [(object) ['src' => asset('favicon-192.png'), 'sizes' => '192x192', 'type' => 'image/png']],
        ];
    }

    /**
     * Serve an uploaded banner or icon.
     */
    public function getImageAction(ServerRequestInterface $request): ResponseInterface
    {
        $file = Validator::queryParams($request)->isInArray(self::imageFiles())->string('file');
        $path = self::DATA_FOLDER . $file;
        $data = Registry::filesystem()->data();

        if (!$data->fileExists($path)) {
            throw new HttpNotFoundException();
        }

        return response($data->read($path), StatusCodeInterface::STATUS_OK, [
            'cache-control' => 'public,max-age=31536000',
            'content-type'  => str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg',
        ]);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title'           => $this->title(),
            'accent'          => $this->accent(),
            'banner'          => $this->banner(),
            'banner_title'    => $this->getPreference('banner-title') === '1',
            'banner_focus'    => $this->bannerFocus(),
            'chart_ages'      => $this->getPreference('chart-ages') === '1',
            'footer_credit'   => $this->getPreference('footer-credit', '1') === '1',
            'icon'            => $this->hasIcon() ? $this->iconUrl(192) : null,
            'app_name'        => $this->getPreference('app-name'),
            'app_short_name'  => $this->getPreference('app-short-name'),
            'app_description' => $this->getPreference('app-description'),
            'theme_menu'      => $this->getPreference('theme-menu', '1') === '1',
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = Validator::parsedBody($request);
        $accent = strtolower($params->string('accent'));

        if (preg_match('/^#[0-9a-f]{6}$/', $accent) === 1) {
            $this->setPreference('accent', $accent);
        }

        foreach (self::MAX_LENGTHS as $name => $length) {
            $this->setPreference($name, mb_substr(trim($params->string($name)), 0, $length));
        }

        $this->setPreference('banner-title', $params->boolean('banner-title', false) ? '1' : '0');
        $this->setPreference('banner-focus', $params->isInArray(array_map(strval(...), array_keys(self::BANNER_FOCUS)))->string('banner-focus', '50'));
        $this->setPreference('chart-ages', $params->boolean('chart-ages', false) ? '1' : '0');
        $this->setPreference('footer-credit', $params->boolean('footer-credit', false) ? '1' : '0');
        $this->setPreference('theme-menu', $params->boolean('theme-menu', false) ? '1' : '0');

        try {
            if ($params->boolean('remove-banner', false)) {
                $this->deleteImages(['banner.jpg']);
                $this->setPreference('banner', '');
            } else {
                $upload = $this->upload($request, 'banner');

                if ($upload !== null) {
                    $this->saveBanner($upload);
                }
            }

            if ($params->boolean('remove-icon', false)) {
                $this->deleteImages(array_map(static fn (int $size): string => 'icon-' . $size . '.png', self::ICON_SIZES));
                $this->setPreference('icon', '');
            } else {
                $upload = $this->upload($request, 'icon');

                if ($upload !== null) {
                    $this->saveIcon($upload);
                }
            }
        } catch (InvalidArgumentException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');

            return redirect($this->getConfigLink());
        }

        $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
        FlashMessages::addMessage($message, 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * Whether this is the theme of the current page. Every enabled global module adds to the page,
     * whichever theme it uses.
     */
    private function inUse(): bool
    {
        $container = Registry::container();

        return $container->has(ModuleThemeInterface::class) && $container->get(ModuleThemeInterface::class)->name() === $this->name();
    }

    private function accent(): string
    {
        $accent = $this->getPreference('accent');

        return preg_match('/^#[0-9a-f]{6}$/', $accent) === 1 ? $accent : self::DEFAULT_ACCENT;
    }

    /**
     * The banner's URL and size, or null when there is none.
     *
     * @return array{url:string,width:int,height:int}|null
     */
    private function banner(): array|null
    {
        // "width height upload-time"
        $parts = explode(' ', $this->getPreference('banner'));

        if (count($parts) !== 3) {
            return null;
        }

        [$width, $height, $version] = array_map(intval(...), $parts);

        return ['url' => $this->imageUrl('banner.jpg', $version), 'width' => $width, 'height' => $height];
    }

    private function bannerFocus(): string
    {
        $focus = $this->getPreference('banner-focus', '50');

        return array_key_exists($focus, self::BANNER_FOCUS) ? $focus : '50';
    }

    private function hasIcon(): bool
    {
        return $this->getPreference('icon') !== '';
    }

    private function iconUrl(int $size): string
    {
        return $this->imageUrl('icon-' . $size . '.png', (int) $this->getPreference('icon'));
    }

    // The upload time in the URL gives each new image a new URL, so it can be cached for a year
    private function imageUrl(string $file, int $version): string
    {
        return route('module', [
            'module' => $this->name(),
            'action' => 'Image',
            'file'   => $file,
            'v'      => $version,
        ]);
    }

    /**
     * @return array<string>
     */
    private static function imageFiles(): array
    {
        return ['banner.jpg', ...array_map(static fn (int $size): string => 'icon-' . $size . '.png', self::ICON_SIZES)];
    }

    /**
     * The contents of an uploaded file, or null when none was chosen.
     */
    private function upload(ServerRequestInterface $request, string $name): string|null
    {
        $file = $request->getUploadedFiles()[$name] ?? null;

        if (!$file instanceof UploadedFileInterface || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('That image is larger than the server accepts.');
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The image could not be uploaded.');
        }

        return $file->getStream()->getContents();
    }

    /**
     * Decode an uploaded JPEG, PNG, GIF or WebP. Anything else (SVG, HTML...) is refused,
     * and the images are re-encoded, so only the pixels are ever served.
     */
    private function decodeImage(string $data): GdImage
    {
        $info = @getimagesizefromstring($data);

        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Choose a JPEG, PNG, GIF or WebP image.');
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('That image is too big to process (over 16 megapixels). Make it smaller and try again.');
        }

        $image = @imagecreatefromstring($data);

        if ($image === false) {
            throw new InvalidArgumentException('That image could not be read.');
        }

        return $image;
    }

    private function saveBanner(string $data): void
    {
        $image  = $this->decodeImage($data);
        $width  = min(imagesx($image), self::BANNER_MAX_WIDTH);
        $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));

        // White behind any transparency, as JPEG has none
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($canvas, null, 85);
        Registry::filesystem()->data()->write(self::DATA_FOLDER . 'banner.jpg', ob_get_clean());

        $this->setPreference('banner', $width . ' ' . $height . ' ' . time());
    }

    private function saveIcon(string $data): void
    {
        $image = $this->decodeImage($data);

        // The middle square of the image
        $side = min(imagesx($image), imagesy($image));
        $left = intdiv(imagesx($image) - $side, 2);
        $top  = intdiv(imagesy($image) - $side, 2);

        if ($side < self::ICON_MIN_SIZE) {
            throw new InvalidArgumentException('Use an icon at least ' . self::ICON_MIN_SIZE . ' pixels across (512 is best).');
        }

        foreach (self::ICON_SIZES as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);

            // iOS puts transparent home-screen icons on black, so that one gets white behind it
            $background = $size === 180 ? imagecolorallocate($canvas, 255, 255, 255) : imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $background);
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $image, 0, 0, $left, $top, $size, $size, $side, $side);

            ob_start();
            imagepng($canvas, null, 9);
            Registry::filesystem()->data()->write(self::DATA_FOLDER . 'icon-' . $size . '.png', ob_get_clean());
        }

        $this->setPreference('icon', (string) time());
    }

    /**
     * @param array<string> $files
     */
    private function deleteImages(array $files): void
    {
        $data = Registry::filesystem()->data();

        foreach ($files as $file) {
            if ($data->fileExists(self::DATA_FOLDER . $file)) {
                $data->delete(self::DATA_FOLDER . $file);
            }
        }
    }

    /**
     * A url() for the <style> block. The URLs come from webtrees' routes, but where webtrees
     * has no base_url configured they include the request's Host header, so keep them from
     * closing the quotes or the block.
     */
    private static function cssUrl(string $url): string
    {
        return "url('" . strtr($url, ["'" => '%27', '\\' => '%5C', '<' => '%3C', '>' => '%3E', "\n" => '', "\r" => '']) . "')";
    }

    /**
     * WCAG relative luminance (0 black to 1 white). Above 0.179, dark text has the better contrast.
     */
    private static function luminance(int $red, int $green, int $blue): float
    {
        $linear = static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linear($red) + 0.7152 * $linear($green) + 0.0722 * $linear($blue);
    }
}
