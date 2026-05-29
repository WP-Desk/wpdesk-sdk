<?php

declare( strict_types=1 );

namespace WPDesk\ExternalScripts;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use RuntimeException;

/**
 * Builds the static, scoped integration bundle after composer create-project.
 */
final class BuilderScript {

	private const BUILD_DIR = '.wpdesk-build';

	/**
	 * @internal
	 */
	public static function postCreateProject( Event $event ): void {
		$io          = $event->getIO();
		$project_dir = self::normalize_path( (string) getcwd() );
		$process     = new ProcessExecutor( $io );
		$filesystem  = new Filesystem( $process );

		$io->write( '<info>Preparing WP Desk integration bundle.</info>' );

		$context = self::collect_context( $io, $project_dir );
		$build_dir = $project_dir . DIRECTORY_SEPARATOR . self::BUILD_DIR;
		$scoped_dir = $build_dir . DIRECTORY_SEPARATOR . 'scoped';

		if ( is_dir( $build_dir ) ) {
			$filesystem->removeDirectory( $build_dir );
		}
		$filesystem->ensureDirectoryExists( $build_dir );

		self::write_file( $project_dir . DIRECTORY_SEPARATOR . 'wpdesk-integration.php', self::create_entrypoint( $context ) );
		self::write_file( $build_dir . DIRECTORY_SEPARATOR . 'scoper.inc.php', self::create_scoper_config( $context ) );

		self::run_php_scoper( $process, $project_dir, $build_dir . DIRECTORY_SEPARATOR . 'scoper.inc.php', $scoped_dir );
		self::write_file( $scoped_dir . DIRECTORY_SEPARATOR . 'composer.json', self::create_runtime_composer_json() );
		self::dump_runtime_autoload( $process, $project_dir, $scoped_dir );

		foreach ( [ 'composer.json', 'composer.lock' ] as $file ) {
			$path = $scoped_dir . DIRECTORY_SEPARATOR . $file;
			if ( is_file( $path ) && ! unlink( $path ) ) {
				throw new RuntimeException( sprintf( 'Cannot remove file "%s".', $path ) );
			}
		}

		self::replace_project_with_output( $filesystem, $project_dir, $scoped_dir );
		self::print_success_message( $io );
	}

	/**
	 * @return array<string, string>
	 */
	private static function collect_context( IOInterface $io, string $project_dir ): array {
		if ( basename( $project_dir ) !== 'wpdesk-integration' ) {
			throw new RuntimeException( 'Run this from a plugin root with: composer create-project wpdesk/wpdesk-external-integration wpdesk-integration --remove-vcs' );
		}

		$plugin_root = self::normalize_path( dirname( $project_dir ) );
		$plugin_slug = basename( $plugin_root );
		$plugin_file = self::resolve_plugin_file( $io, $plugin_root, $plugin_slug );
		$plugin_name = self::read_plugin_name( $plugin_root . DIRECTORY_SEPARATOR . $plugin_file ) ?: $plugin_slug;
		$product_id = trim( (string) $io->ask( 'WP Desk product ID [' . $plugin_name . '] (must match WP Desk licensing): ', $plugin_name ) );
		if ( $product_id === '' ) {
			throw new RuntimeException( 'Product ID cannot be empty.' );
		}
		$prefix = self::detect_prefix( $plugin_root, $plugin_slug );
		$io->write( 'Using PHP namespace prefix: ' . $prefix );

		return [
			'plugin_file' => $plugin_file,
			'product_id'  => $product_id,
			'prefix'      => $prefix,
		];
	}

	private static function resolve_plugin_file( IOInterface $io, string $plugin_root, string $plugin_slug ): string {
		if ( ! is_dir( $plugin_root ) ) {
			throw new RuntimeException( sprintf( 'Plugin root "%s" does not exist.', $plugin_root ) );
		}

		$candidates = self::find_plugin_file_candidates( $plugin_root );
		if ( count( $candidates ) === 0 ) {
			throw new RuntimeException( sprintf( 'Plugin root "%s" does not contain a root PHP file with a Plugin Name header.', $plugin_root ) );
		}

		$expected = $plugin_slug . '.php';
		if ( in_array( $expected, $candidates, true ) ) {
			return $expected;
		}

		if ( count( $candidates ) === 1 ) {
			return reset( $candidates );
		}

		$default = reset( $candidates );
		$io->write( '<info>Detected plugin files:</info>' );
		foreach ( $candidates as $candidate ) {
			$io->write( ' - ' . $candidate );
		}
		$plugin_file = (string) $io->ask( 'Main plugin file [' . $default . ']: ', $default );
		$plugin_file = trim( str_replace( [ '\\', '/' ], DIRECTORY_SEPARATOR, $plugin_file ), DIRECTORY_SEPARATOR . " \t\n\r\0\x0B" );

		if ( ! in_array( $plugin_file, $candidates, true ) ) {
			throw new RuntimeException( 'Choose one of the detected root plugin files.' );
		}

		return $plugin_file;
	}

	/**
	 * @return string[]
	 */
	private static function find_plugin_file_candidates( string $plugin_root ): array {
		$candidates = [];
		$files = glob( $plugin_root . DIRECTORY_SEPARATOR . '*.php' );

		if ( ! is_array( $files ) ) {
			return [];
		}

		foreach ( $files as $file ) {
			if ( self::read_plugin_name( $file ) !== '' ) {
				$candidates[] = basename( $file );
			}
		}

		sort( $candidates );

		return $candidates;
	}

	private static function read_plugin_name( string $plugin_file ): string {
		$contents = file_get_contents( $plugin_file, false, null, 0, 8192 );
		if ( ! is_string( $contents ) ) {
			return '';
		}

		if ( preg_match( '/^[ \t\/*#@]*Plugin Name:\s*(.+)$/mi', $contents, $matches ) !== 1 ) {
			return '';
		}

		return trim( (string) $matches[1] );
	}

	private static function detect_prefix( string $plugin_root, string $plugin_slug ): string {
		$prefix = self::detect_parent_namespace_prefix( $plugin_root );
		if ( $prefix === '' ) {
			$prefix = self::studly_namespace_from_slug( $plugin_slug ) . '\\Vendor';
		}

		if ( ! self::is_valid_namespace( $prefix ) ) {
			throw new RuntimeException( sprintf( 'PHP namespace prefix "%s" is not valid.', $prefix ) );
		}

		return $prefix;
	}

	private static function detect_parent_namespace_prefix( string $plugin_root ): string {
		$composer_json = $plugin_root . DIRECTORY_SEPARATOR . 'composer.json';
		if ( ! is_file( $composer_json ) ) {
			return '';
		}

		$contents = file_get_contents( $composer_json );
		if ( ! is_string( $contents ) ) {
			return '';
		}

		$composer = json_decode( $contents, true );
		if ( ! is_array( $composer ) ) {
			return '';
		}

		foreach ( [ 'autoload', 'autoload-dev' ] as $autoload_key ) {
			if ( ! isset( $composer[ $autoload_key ]['psr-4'] ) || ! is_array( $composer[ $autoload_key ]['psr-4'] ) ) {
				continue;
			}

			foreach ( array_keys( $composer[ $autoload_key ]['psr-4'] ) as $namespace ) {
				$namespace = trim( (string) $namespace, '\\' );
				if ( self::is_valid_namespace( $namespace ) ) {
					return $namespace . '\\Vendor';
				}
			}
		}

		return '';
	}

	private static function studly_namespace_from_slug( string $slug ): string {
		$parts = preg_split( '/[^A-Za-z0-9_]+/', $slug );
		if ( ! is_array( $parts ) ) {
			return 'Plugin';
		}

		$namespace = '';
		foreach ( $parts as $part ) {
			$part = preg_replace( '/[^A-Za-z0-9_]/', '', $part );
			if ( ! is_string( $part ) || $part === '' ) {
				continue;
			}
			$namespace .= ucfirst( strtolower( $part ) );
		}

		if ( $namespace === '' ) {
			$namespace = 'Plugin';
		}

		if ( preg_match( '/^[0-9]/', $namespace ) === 1 ) {
			$namespace = 'Plugin' . $namespace;
		}

		return $namespace;
	}

	private static function is_valid_namespace( string $namespace ): bool {
		return preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace ) === 1;
	}

	/**
	 * @param array<string, string> $context
	 */
	private static function create_entrypoint( array $context ): string {
		$product_id = var_export( $context['product_id'], true );
		$plugin_file = var_export( '/' . $context['plugin_file'], true );

		return <<<PHP
<?php

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

\$product_id  = {$product_id};
\$plugin_file = dirname( __DIR__ ) . {$plugin_file};

\\WPDesk\\External\\ExternalIntegration::integrate( \$product_id, \$plugin_file );

PHP;
	}

	/**
	 * @param array<string, string> $context
	 */
	private static function create_scoper_config( array $context ): string {
		$prefix = var_export( $context['prefix'], true );

		return <<<PHP
<?php

declare(strict_types=1);

use Isolated\\Symfony\\Component\\Finder\\Finder;

\$vendorDir = dirname(__DIR__) . '/vendor';
\$loadExcludes = static function (string \$file): array {
	\$contents = file_get_contents(\$file);
	if (!is_string(\$contents)) {
		return [];
	}

	\$decoded = json_decode(\$contents, true);

	return is_array(\$decoded) ? \$decoded : [];
};

return [
	'prefix' => {$prefix},
	'exclude-classes' => array_merge(
		\$loadExcludes(\$vendorDir . '/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-classes.json'),
		\$loadExcludes(\$vendorDir . '/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-interfaces.json'),
	),
	'exclude-functions' => array_merge(
		\$loadExcludes(\$vendorDir . '/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-functions.json'),
	),
	'exclude-constants' => array_merge(
		\$loadExcludes(\$vendorDir . '/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-constants.json'),
	),
	'finders' => [
		Finder::create()
			->files()
			->ignoreVCS(true)
			->notName('/LICENSE|.*\\\\.md|.*\\\\.dist|Makefile|composer\\\\.json|composer\\\\.lock/')
			->exclude([
				'doc',
				'test',
				'test_old',
				'tests',
				'Tests',
				'vendor-bin',
			])
			->in([
				'src',
				'vendor/wpdesk/wp-wpdesk-license',
				'vendor/psr/log',
			]),
		Finder::create()->append([
			'wpdesk-integration.php',
		]),
	],
];

PHP;
	}

	private static function create_runtime_composer_json(): string {
		return <<<JSON
{
  "name": "wpdesk/wpdesk-external-integration-runtime",
  "description": "Generated WP Desk external integration runtime bundle.",
  "type": "library",
  "autoload": {
    "classmap": [
      "src",
      "vendor/psr",
      "vendor/wpdesk"
    ]
  }
}

JSON;
	}

	private static function run_php_scoper( ProcessExecutor $process, string $project_dir, string $config_file, string $output_dir ): void {
		$php_scoper = $project_dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php-scoper';
		if ( ! is_file( $php_scoper ) ) {
			throw new RuntimeException( 'PHP-Scoper is not installed. Run create-project without --no-dev.' );
		}

		$exit_code = $process->execute(
			[
				PHP_BINARY,
				'-d',
				'error_reporting=8191',
				$php_scoper,
				'add-prefix',
				'--output-dir',
				$output_dir,
				'--config',
				$config_file,
				'--force',
			],
			$output,
			$project_dir
		);

		if ( $exit_code !== 0 ) {
			throw new RuntimeException( "PHP-Scoper failed:\n" . $output . $process->getErrorOutput() );
		}
	}

	private static function dump_runtime_autoload( ProcessExecutor $process, string $project_dir, string $output_dir ): void {
		$composer = $project_dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'composer';
		$command = is_file( $composer )
			? [ PHP_BINARY, $composer ]
			: [ 'composer' ];
		$command = array_merge(
			$command,
			[
				'dump-autoload',
				'--working-dir',
				$output_dir,
				'--optimize',
				'--no-dev',
				'--no-scripts',
			]
		);

		$exit_code = $process->execute( $command, $output, $project_dir );

		if ( $exit_code !== 0 ) {
			throw new RuntimeException( "Composer autoload dump failed:\n" . $output . $process->getErrorOutput() );
		}
	}

	private static function replace_project_with_output( Filesystem $filesystem, string $project_dir, string $output_dir ): void {
		$final_dir = $project_dir . DIRECTORY_SEPARATOR . '.wpdesk-final';

		if ( is_dir( $final_dir ) ) {
			$filesystem->removeDirectory( $final_dir );
		}

		$filesystem->rename( $output_dir, $final_dir );

		$entries = scandir( $project_dir );
		if ( ! is_array( $entries ) ) {
			throw new RuntimeException( sprintf( 'Cannot read project directory "%s".', $project_dir ) );
		}

		foreach ( $entries as $entry ) {
			if ( $entry === '.' || $entry === '..' || $entry === basename( $final_dir ) ) {
				continue;
			}

			$filesystem->remove( $project_dir . DIRECTORY_SEPARATOR . $entry );
		}

		$filesystem->copy( $final_dir, $project_dir );
		$filesystem->removeDirectory( $final_dir );
	}

	private static function print_success_message( IOInterface $io ): void {
		$io->write(
			[
				'',
				'<info>WP Desk integration bundle is ready.</info>',
				'Add this line to your main plugin file:',
				'',
				"require_once __DIR__ . '/wpdesk-integration/wpdesk-integration.php';",
				'',
			]
		);
	}

	private static function write_file( string $path, string $contents ): void {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0777, true ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( sprintf( 'Cannot create directory "%s".', $directory ) );
		}

		if ( file_put_contents( $path, $contents ) === false ) {
			throw new RuntimeException( sprintf( 'Cannot write file "%s".', $path ) );
		}
	}

	private static function normalize_path( string $path ): string {
		$path = rtrim( $path, "/\\" );
		$real_path = realpath( $path );

		return is_string( $real_path ) ? $real_path : $path;
	}
}
