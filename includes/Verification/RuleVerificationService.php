<?php
/**
 * Persists per-rule runtime verification snapshots.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Verification;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\RulesRepository;
use AssetControl\Helpers\Logger;

/**
 * Verifies rules at condition-appropriate URLs and stores results on the rule row.
 */
final class RuleVerificationService {

	public function __construct(
		private readonly RuntimeVerificationService $runtime = new RuntimeVerificationService(),
		private readonly RuleVerificationUrlResolver $url_resolver = new RuleVerificationUrlResolver(),
		private readonly RulesRepository $repository = new RulesRepository(),
		private readonly HTMLVerificationParser $parser = new HTMLVerificationParser()
	) {}

	/**
	 * @param array<string, mixed> $rule
	 * @return array<string, mixed>
	 */
	public function verify_and_store( array $rule ): array {
		$snapshot = $this->build_snapshot( $rule );
		$id       = (int) ( $rule['id'] ?? 0 );
		if ( $id > 0 ) {
			$this->repository->update_verification( $id, $snapshot );
		}
		return $snapshot;
	}

	/**
	 * @param array<int, int>|null $rule_ids Null = all rules.
	 * @return array<int, array<string, mixed>> rule_id => snapshot
	 */
	public function verify_many( ?array $rule_ids = null ): array {
		$rules = $this->repository->all_cached();
		if ( null !== $rule_ids ) {
			$ids   = array_flip( array_map( 'intval', $rule_ids ) );
			$rules = array_values(
				array_filter(
					$rules,
					static fn( array $rule ): bool => isset( $ids[ (int) ( $rule['id'] ?? 0 ) ] )
				)
			);
		}

		$by_id = array();
		foreach ( $rules as $rule ) {
			$id = (int) ( $rule['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$by_id[ $id ] = $this->verify_and_store( $rule );
		}

		return $by_id;
	}

	/**
	 * @param array<string, mixed> $rule
	 * @return array<string, mixed>
	 */
	private function build_snapshot( array $rule ): array {
		$url = $this->url_resolver->resolve( $rule );

		if ( empty( $rule['enabled'] ) ) {
			return $this->snapshot(
				$rule,
				RuntimeVerificationService::STATUS_SKIPPED,
				__( 'Rule is disabled.', 'assetpilot' ),
				'',
				'',
				$url
			);
		}

		$fetch = $this->parser->fetch_and_parse( $url );
		if ( '' !== $fetch['error'] ) {
			return $this->snapshot(
				$rule,
				RuntimeVerificationService::STATUS_UNAVAILABLE,
				$this->friendly_fetch_error( (string) $fetch['error'], $url ),
				'',
				'',
				$url
			);
		}

		$result = $this->runtime->verify_rule( $rule, $fetch['parsed'], $url );

		return $this->snapshot(
			$rule,
			(string) ( $result['status'] ?? RuntimeVerificationService::STATUS_PARTIAL ),
			(string) ( $result['message'] ?? '' ),
			(string) ( $result['expected'] ?? '' ),
			(string) ( $result['actual'] ?? '' ),
			$url
		);
	}

	/**
	 * @param array<string, mixed> $rule
	 * @return array<string, mixed>
	 */
	private function snapshot(
		array $rule,
		string $status,
		string $message,
		string $expected,
		string $actual,
		string $url
	): array {
		$snapshot = array(
			'rule_id'     => (int) ( $rule['id'] ?? 0 ),
			'status'      => $status,
			'message'     => $message,
			'expected'    => $expected,
			'actual'      => $actual,
			'url'         => $url,
			'verified_at' => gmdate( 'c' ),
		);

		$this->log_verification( $rule, $snapshot );

		return $snapshot;
	}

	private function friendly_fetch_error( string $error, string $url ): string {
		if ( '' === $error ) {
			return $error;
		}

		if ( str_contains( $error, 'cURL error 28' ) || stripos( $error, 'timed out' ) !== false ) {
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( '' !== $host && preg_match( '/\.(local|test)$/i', $host ) ) {
				return __(
					'Could not fetch the page in time (common on local .local URLs). Open the site in your browser, then click Re-verify all.',
					'assetpilot'
				);
			}
		}

		return $error;
	}

	/**
	 * @param array<string, mixed> $rule
	 * @param array<string, mixed> $snapshot
	 */
	private function log_verification( array $rule, array $snapshot ): void {
		if ( ! Logger::is_enabled() ) {
			return;
		}

		$status = (string) ( $snapshot['status'] ?? '' );
		if ( RuntimeVerificationService::STATUS_VERIFIED === $status ) {
			Logger::log(
				'verification',
				(string) ( $snapshot['message'] ?: __( 'Verification passed.', 'assetpilot' ) ),
				array(
					'rule_id'      => (int) ( $rule['id'] ?? 0 ),
					'asset_handle' => (string) ( $rule['asset_handle'] ?? '' ),
					'status'       => $status,
					'url'          => (string) ( $snapshot['url'] ?? '' ),
					'severity'     => 'info',
				)
			);
			return;
		}

		Logger::log(
			'verification',
			(string) ( $snapshot['message'] ?: __( 'Verification did not pass.', 'assetpilot' ) ),
			array(
				'rule_id'      => (int) ( $rule['id'] ?? 0 ),
				'asset_handle' => (string) ( $rule['asset_handle'] ?? '' ),
				'status'       => $status,
				'url'          => (string) ( $snapshot['url'] ?? '' ),
				'expected'     => (string) ( $snapshot['expected'] ?? '' ),
				'actual'       => (string) ( $snapshot['actual'] ?? '' ),
				'severity'     => RuntimeVerificationService::STATUS_SKIPPED === $status ? 'debug' : 'warning',
			)
		);
	}
}
