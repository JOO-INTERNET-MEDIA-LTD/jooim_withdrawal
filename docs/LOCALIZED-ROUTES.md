# Localized public routes

JOOIM Withdrawal registers a separate readable route rule for each active shop language. Route segments use stable ASCII transliteration so they remain safe in web servers, exports and third-party link-list modules.

## Active-language examples

| Language | Order-reference flow | Direct withdrawal |
|---|---|---|
| Slovak | `/sk/odstupenie-od-zmluvy` | `/sk/odstupenie-od-zmluvy/priamo` |
| English | `/en/contract-withdrawal` | `/en/contract-withdrawal/direct` |
| Ukrainian | `/uk/vidmova-vid-dohovoru` | `/uk/vidmova-vid-dohovoru/bezposeredno` |
| German | `/de/vertragswiderruf` | `/de/vertragswiderruf/direkt` |

The module configuration page generates the final absolute URL with the real shop domain, base URI, language ID and shop ID. Do not hard-code a domain from this document into a different shop.

## Base route map

| ISO | Localized base slug | Direct suffix |
|---|---|---|
| bg | `otkaz-ot-dogovora` | `direktno` |
| cs | `odstoupeni-od-smlouvy` | `primo` |
| da | `fortrydelse-af-aftale` | `direkte` |
| de | `vertragswiderruf` | `direkt` |
| el | `ypanachorisi-apo-symvasi` | `amesa` |
| en | `contract-withdrawal` | `direct` |
| es | `desistimiento-del-contrato` | `directo` |
| et | `lepingust-taganemine` | `otse` |
| fi | `sopimuksen-peruuttaminen` | `suoraan` |
| fr | `retractation-du-contrat` | `direct` |
| ga | `tarraingt-siar-on-gconradh` | `direach` |
| hr | `odustajanje-od-ugovora` | `izravno` |
| hu | `szerzodestol-elallas` | `kozvetlen` |
| it | `recesso-dal-contratto` | `diretto` |
| lt | `sutarties-atsisakymas` | `tiesiogiai` |
| lv | `atteikums-no-liguma` | `tiesi` |
| mt | `irtirar-mill-kuntratt` | `dirett` |
| nl | `herroeping-van-de-overeenkomst` | `direct` |
| pl | `odstapienie-od-umowy` | `bezposrednio` |
| pt | `resolucao-do-contrato` | `direto` |
| ro | `retragere-din-contract` | `direct` |
| sk | `odstupenie-od-zmluvy` | `priamo` |
| sl | `odstop-od-pogodbe` | `neposredno` |
| sv | `frantrade-fran-avtal` | `direkt` |
| uk | `vidmova-vid-dohovoru` | `bezposeredno` |

Unsupported ISO codes use the English route rule.

## Language-neutral links

PrestaShop **Design → Link List** provides one URL field even when the displayed label is translatable. Use the language-neutral entry controller there:

```text
/index.php?fc=module&module=jooim_withdrawal&controller=entry&target=identify
/index.php?fc=module&module=jooim_withdrawal&controller=entry&target=direct
```

The entry controller selects an active language from an explicit allowed `lang` value, same-origin page context, PrestaShop language cookie, current language, browser preferences and finally the shop default. It then redirects to the localized friendly route.

## Deployment rule

When upgrading from a pre-release same-version test build:

1. open the module configuration once in every shop context;
2. clear `var/cache/prod/*` and `var/cache/dev/*` or clear cache in Back Office;
3. regenerate `.htaccess` by saving **Shop Parameters → Traffic & SEO** when old route cache persists;
4. verify the generated links shown by the module, not manually guessed URLs.

Old foreign-language URLs containing the Slovak slug are not registered as canonical aliases. This avoids duplicate-language URLs and keeps new links semantically correct.
