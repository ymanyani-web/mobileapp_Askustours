<?php
declare(strict_types=1);

// Homepage editorial cards live in the existing configuration, not the service catalog.
function home_sections(): array
{
    return ['tripIdeas'=>'Crea tu viaje', 'destinations'=>'Destinos populares', 'experiences'=>'Experiencias para ti'];
}

function home_configuration(): array
{
    $json = db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn();
    return $json === false ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function home_card_image(array $card): string
{
    return (string)($card['_imagePath'] ?? $card['imageUrl'] ?? '');
}

function home_card_media_url(string $section, array $card): string
{
    if (!empty($card['_mediaId'])) {
        return public_content_media_url('home_'.$section, (int)$card['_mediaId'], home_card_image($card));
    }
    return (string)($card['imageUrl'] ?? '');
}

function home_sorted_cards(array $cards): array
{
    // Preserve existing array order when no explicit editorial order was stored.
    foreach ($cards as $index => &$card) $card['_position'] = $index;
    unset($card);
    usort($cards, fn($a,$b) => (($a['displayOrder'] ?? $a['_position']) <=> ($b['displayOrder'] ?? $b['_position'])) ?: ($a['_position'] <=> $b['_position']));
    foreach ($cards as &$card) unset($card['_position']);
    return $cards;
}

function home_public_cards(string $section, array $cards): array
{
    $result = [];
    foreach (home_sorted_cards($cards) as $card) {
        if (($card['_active'] ?? true) === false || !public_asset_available(home_card_image($card))) continue;
        $card['imageUrl'] = home_card_media_url($section, $card);
        foreach (array_keys($card) as $key) if (str_starts_with((string)$key, '_')) unset($card[$key]);
        $result[] = $card;
    }
    return $result;
}

function home_validate_card(array $input, array $existing, string $section): array
{
    $card = $existing;
    foreach (['title'=>160, 'description'=>1000] as $field=>$maximum) {
        $card[$field] = [];
        foreach (['es','en','ar'] as $locale) {
            $text = trim((string)($input[$field.'_'.$locale] ?? ''));
            if (mb_strlen($text) > $maximum) throw new RuntimeException('El texto supera el tamaño permitido.');
            $card[$field][$locale] = $text;
        }
    }
    if ($card['title']['es'] === '') throw new RuntimeException('Escribe el título en español.');
    $order = filter_var($input['displayOrder'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>100000]]);
    if ($order === false) throw new RuntimeException('El orden debe ser un número entero entre 0 y 100000.');
    $card['displayOrder'] = $order;
    $card['_active'] = isset($input['active']);
    $styles = ['nature','honeymoon','family','friends','shopping','sports'];
    if ($section === 'tripIdeas') {
        $style = (string)($input['prefillStyle'] ?? '');
        if ($style !== '' && !in_array($style, $styles, true)) throw new RuntimeException('Elige un estilo de viaje válido.');
        $card['action'] = 'designTrip';
        $card['prefillStyle'] = $style !== '' ? $style : null;
    } else {
        // These collections have no booking contract yet.
        $card['action'] = 'placeholder';
        $card['prefillStyle'] = null;
    }
    return $card;
}

function home_content_navigation(string $selected): void
{
    $sections = ['banners'=>'Slides principales','offers'=>'Ofertas destacadas',...home_sections()];
    echo '<nav class="home-content-tabs" aria-label="Secciones de la portada">';
    foreach ($sections as $key=>$label) {
        $url = in_array($key,['banners','offers'],true) ? 'content.php?type='.$key : 'home-content.php?section='.$key;
        echo '<a '.($key === $selected ? 'aria-current="page" class="selected" ' : '').'href="'.e($url).'">'.e($label).'</a>';
    }
    echo '<a href="home-publishing.php" '.($selected==='publishing'?'class="selected" aria-current="page"':'').'>Publicación mixta</a></nav>';
}
