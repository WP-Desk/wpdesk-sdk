<?php

namespace WPDesk\ExternalScripts;

use Composer\Script\Event;

class BuilderScript {
	/** @var string */
	private static $plugin_dir;

	public static function build_env( Event $event ) {
		$io             = $event->getIO();
		$scopeNamespace = $io->ask( 'Podaj unikalny identyfikator przestrzeni nazw w formacie camelCase: ' );
		self::file_regex_replace(__DIR__ . "/../scoper.inc.php", "/'prefix'[ ]*=>[ ]*'[^']*'/", "'prefix' => '$scopeNamespace'");

		$product_id     = $io->ask( 'Podaj unikalny identyfikator produktu uzgodniony z WP Desk: ' );
		self::file_regex_replace(__DIR__ . "/../wpdesk-integration.php", '/\$product_id[ ]*=[ ]*\'[^\']*\';/', '$product_id = ' . "'{$product_id}';");
		self::$plugin_dir = rtrim($io->ask( 'Podaj pełną absolutną ścieżkę do katalogu w którym znajduje się wtyczka: ' ), ' /');
		$basename_plugin_dir = basename( self::$plugin_dir );
		self::file_regex_replace(__DIR__ . "/../wpdesk-integration.php", '/\$plugin_dir[ ]*=[ ]*\'[^\']*\';/', '$plugin_dir = ' . "'{$basename_plugin_dir}';");;
	}

	public static function copy_to_plugin() {
		$plugin_dir = self::$plugin_dir;
		$integration_dir = __DIR__ . '/../wpdesk-integration';;
		exec("cp -rf {$integration_dir} {$plugin_dir}/");
	}

	public static function info( Event $event ) {
		$io = $event->getIO();
		$io->write( "\n
-----------------------------------------------------------
Dodaj we wtyczce kod PHP:

require_once __DIR__ . '/wpdesk-integration/wpdesk-integration.php';

aby uruchomić integrację z systemem sprzedażowym WP Desk.
		" );
	}

	/**
	 * SED.
	 *
	 * @param string $file_pattern .
	 * @param string $pattern .
	 * @param string $replace .
	 *
	 * @return string[] array of changed files
	 */
	private static function file_regex_replace( $file_pattern, $pattern, $replace ) {
		$changed_files = [];

		foreach ( glob( $file_pattern ) as $filename ) {
			$input  = file_get_contents( $filename );
			$output = preg_replace( $pattern, $replace, $input );
			if ( $output !== $input ) {
				$changed_files[] = $filename;
				file_put_contents( $filename, $output );
			}
		}

		return $changed_files;
	}
}