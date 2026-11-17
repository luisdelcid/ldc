# Simple HTML DOM Parser 1.9.1 — parcheada y prefijada a `LDC\Deps`

Versión de la librería lista para **bundlear dentro de un plugin de WordPress**
sin chocar con otros plugins que incluyan la 1.9.1 original.

La 1.9.1 oficial define todo en el **espacio global**: funciones (`str_get_html`,
`file_get_html`), clases (`simple_html_dom`, `simple_html_dom_node`) y ~25
constantes `HDOM_*`. Si dos plugins incluyen la original, chocan. Esta build
mueve todo bajo el namespace `LDC\Deps` (lo mismo que te generaría php-scoper /
wpify-scoper), más guardas para que sea segura.

## Contenido

- `simple_html_dom.php` — la librería **parcheada + prefijada**.
- `tests_scoped.php` — verificación (aislamiento, coexistencia, doble inclusión).
- `generar_scoped.py` — script que regenera este archivo desde el parcheado.
- `README_SCOPED.md` — este archivo.

## Cómo se usa en tu plugin

Copia `simple_html_dom.php` a tu plugin (p. ej. `mi-plugin/lib/`), inclúyelo una
vez y llama a las funciones **con el prefijo**:

```php
require_once __DIR__ . '/lib/simple_html_dom.php';

use function LDC\Deps\str_get_html;
use function LDC\Deps\file_get_html;

$dom = str_get_html('<p>Hola <b>mundo</b></p>');
echo $dom->find('b', 0)->plaintext; // "mundo"
```

O sin `use`, con el nombre completo:

```php
$dom = \LDC\Deps\str_get_html($html);
$nodo = $dom->find('.precio', 0);
// Las clases también: new \LDC\Deps\simple_html_dom();
```

> Recuerda del informe de auditoría: `->plaintext` puede contener HTML activo y
> los atributos vienen sin decodificar entidades. Pasa la salida por
> `esc_html()` / `wp_kses()` antes de imprimirla, y no uses esta librería como
> sanitizador.

## Qué garantiza (y qué no)

Verificado con `php tests_scoped.php` (18/18):

1. **Aislamiento.** Tras incluir el archivo, NO existe ningún `simple_html_dom`,
   `str_get_html` ni `HDOM_*` global. Todo está bajo `LDC\Deps`.
2. **Coexistencia.** Puede convivir en la misma petición con la 1.9.1 original
   (global) de otro plugin: `\LDC\Deps\simple_html_dom` y `simple_html_dom` son
   dos clases distintas y ambas funcionan.
3. **Doble inclusión segura.** Las guardas `function_exists()` / `class_exists()`
   y los `defined() || define()` hacen que, si **dos plugins tuyos** (mismo
   prefijo) incluyen el archivo, el segundo no cause un fatal por redeclaración.

**Límite a tener en cuenta:** como el prefijo es el mismo (`LDC\Deps`) en todos
tus plugins, si dos de ellos bundlean este archivo, **gana la copia que se cargue
primero** (las demás se saltan por las guardas). Mantén la **misma copia/versión**
en todos tus plugins; si en algún momento necesitas aislamiento estricto por
versión, usa un sub-namespace versionado (p. ej. `LDC\Deps\SimpleHtmlDom_1_9_1`).
Esto es inherente a bundlear con un prefijo compartido, igual que con tu scoper.

## Relación con tu flujo de wpify/scoper

Esta librería **no está en Packagist**, así que no entra por el `vendor/` que tu
scoper procesa. Este archivo es el **equivalente ya generado** a lo que php-scoper
produciría (clase global → `LDC\Deps\simple_html_dom`, función global →
`LDC\Deps\str_get_html`, constante → `LDC\Deps\HDOM_*`), por eso es consistente
con el resto de tus dependencias prefijadas y lo puedes llamar igual.

Si prefirieras que pase por tu pipeline en vez de copiarlo a mano, la forma usual
es declararla como paquete local con un repositorio `path` en el `composer.json`
del plugin y dejar que el scoper la prefije junto a lo demás. Un `composer.json`
mínimo para la carpeta de la versión parcheada (la NO prefijada) sería:

```json
{
    "name": "ldc/simplehtmldom-patched",
    "version": "1.9.1-ldc.1",
    "autoload": { "files": ["simple_html_dom.php"] }
}
```

y en tu plugin:

```json
{
    "repositories": [
        { "type": "path", "url": "../simplehtmldom_1_9_1_patched" }
    ],
    "require": { "ldc/simplehtmldom-patched": "*" }
}
```

En ese caso **no** uses el `simple_html_dom.php` de ESTA carpeta (ya prefijado):
deja que el scoper prefije el de la carpeta `_patched`, para no prefijar dos
veces. Las dos rutas llevan al mismo sitio —`\LDC\Deps\str_get_html`—; elige una.

## Regenerar

Si vuelves a tocar la versión parcheada, regenera esta con:

```bash
python3 generar_scoped.py
```

(desde la raíz del proyecto; lee `simplehtmldom_1_9_1_patched/simple_html_dom.php`
y reescribe `simplehtmldom_1_9_1_patched_scoped/simple_html_dom.php`). Para
cambiar el prefijo, edita la variable del namespace en ese script.
