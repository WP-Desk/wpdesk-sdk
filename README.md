# Integracja wtyczki z systemem licencji WP Desk

Ta paczka umożliwia szybką integrację wtyczki WordPress z systemem licencji i aktualizacji WP Desk. Wersja produkcyjna kodu (wraz z bibliotekami zależnymi) jest automatycznie izolowana (scoped) przy użyciu narzędzia PHP-Scoper. Chroni to przed konfliktami z innymi wtyczkami korzystającymi z tych samych bibliotek.

## Wymagania

- Lokalnie zainstalowany **PHP >= 7.4**.
- Lokalnie zainstalowany **Composer** (dostępny globalnie w terminalu).
- System operacyjny z rodziny Unix (**Linux / macOS**).

### Wymogi dotyczące wtyczki (Nagłówki i Changelog)
Aby integracja oraz system aktualizacji działały poprawnie, główny plik Twojej wtyczki powinien zawierać standardowe nagłówki WordPress i WooCommerce:
- `Requires at least` (minimalna wersja WordPress)
- `Tested up to` (maksymalna przetestowana wersja WordPress)
- `Requires PHP` (minimalna wersja PHP)
- `WC requires at least` (minimalna wersja WooCommerce)
- `WC tested up to` (maksymalna przetestowana wersja WooCommerce)

Dodatkowo, w głównym katalogu wtyczki musi znajdować się plik `changelog.txt` przygotowany zgodnie z formatem [Keep a Changelog](https://keepachangelog.com/).

---

## Instrukcja instalacji

### Krok 1: Uruchomienie instalatora
Przejdź w terminalu do **głównego katalogu swojej wtyczki** (np. `wp-content/plugins/moja-wtyczka/`) i wykonaj poniższe polecenie:

```bash
composer create-project wpdesk/wpdesk-external-integration wpdesk-integration
```

> [!IMPORTANT]
> Docelowy katalog **musi** nazywać się dokładnie `wpdesk-integration`. Skrypt instalacyjny wymaga tej nazwy do poprawnego działania.

### Krok 2: Konfiguracja interaktywna
Instalator automatycznie przeanalizuje wtyczkę, wykryje główny plik PHP oraz przygotuje unikalną przestrzeń nazw dla zależności (na podstawie pliku `composer.json` lub nazwy katalogu).

Zostaniesz poproszony o:
1. **WP Desk product ID** – podaj unikalny identyfikator produktu uzgodniony z WP Desk (domyślnie sugerowana jest nazwa wtyczki odczytana z nagłówków pliku głównego).
2. *Opcjonalnie:* Jeśli wtyczka posiada wiele plików z nagłówkiem `Plugin Name` w katalogu głównym, instalator poprosi o wskazanie, który z nich jest plikiem głównym.

Po zakończeniu konfiguracji i budowania, skrypt automatycznie usunie pliki instalatora, pozostawiając w katalogu `wpdesk-integration` wyłącznie gotową, zoptymalizowaną paczkę produkcyjną.

### Krok 3: Dołączenie integracji w kodzie wtyczki
W głównym pliku swojej wtyczki (tym z nagłówkami WordPressa) dodaj następującą linię kodu:

```php
require_once __DIR__ . '/wpdesk-integration/wpdesk-integration.php';
```

Katalog `wpdesk-integration` powinien zostać dodany do Twojego repozytorium i spakowany razem z wtyczką do dystrybucji.
