/**
 * User-facing messages for WordPress REST / apiFetch failures.
 */
import { __ } from '@wordpress/i18n';

const AUTH_ERROR_CODES = new Set( [
	'rest_forbidden',
	'rest_not_logged_in',
	'rest_cookie_invalid_nonce',
	'rest_authorization_required',
	'rest_cannot_create',
	'rest_cannot_edit',
] );

/**
 * @param {unknown} err
 * @returns {Record<string, unknown>|null}
 */
function parseEmbeddedJson( err ) {
	if ( ! err || typeof err !== 'object' ) {
		return null;
	}

	const raw = err.message;
	if ( 'string' !== typeof raw || ! raw.trim().startsWith( '{' ) ) {
		return null;
	}

	try {
		const parsed = JSON.parse( raw );
		return parsed && typeof parsed === 'object' ? parsed : null;
	} catch {
		return null;
	}
}

/**
 * @param {unknown} err
 * @returns {{ code: string, status: number, message: string }}
 */
function normalizeApiError( err ) {
	const embedded = parseEmbeddedJson( err );
	const source = embedded || err || {};

	const code =
		( typeof source.code === 'string' && source.code ) ||
		( typeof source?.data?.code === 'string' && source.data.code ) ||
		'';

	const status =
		Number( source?.data?.status ) ||
		Number( source?.status ) ||
		Number( err?.data?.status ) ||
		0;

	const message =
		( typeof source.message === 'string' && source.message ) ||
		( typeof err?.message === 'string' && err.message ) ||
		'';

	return { code, status, message };
}

function isRestAuthFailure( { code, status, message } ) {
	if ( AUTH_ERROR_CODES.has( code ) ) {
		return true;
	}

	if ( 401 === status || 403 === status ) {
		return true;
	}

	return message === 'Sorry, you are not allowed to do that.';
}

function restAuthFailureMessage() {
	return __(
		'WordPress rejected this request (REST API not authorized — often HTTP 401). A security plugin, firewall, or authentication plugin (such as JSON Basic Authentication) may be blocking wp-json calls from the admin. Reload this page while logged in, allow REST access for administrators, and whitelist /wp-json/assetpilot/v1/* (or disable REST restrictions for logged-in admins). If you use JSON Basic Authentication, ensure Application Passwords or cookie auth still works for wp-admin REST requests.',
		'assetpilot'
	);
}

/**
 * @param {unknown} err
 * @param {string} fallback
 * @returns {string}
 */
export function getApiErrorMessage( err, fallback = '' ) {
	if ( ! err ) {
		return fallback;
	}

	const normalized = normalizeApiError( err );

	if ( isRestAuthFailure( normalized ) ) {
		return restAuthFailureMessage();
	}

	if ( normalized.message && ! normalized.message.trim().startsWith( '{' ) ) {
		return normalized.message;
	}

	return fallback;
}

/**
 * Attach a clearer message to apiFetch errors for UI display.
 *
 * @param {unknown} err
 * @returns {unknown}
 */
export function enrichApiError( err ) {
	const friendly = getApiErrorMessage( err, '' );

	if ( friendly && err && typeof err === 'object' && friendly !== err.message ) {
		err.originalMessage = err.message;
		err.message = friendly;
	}

	return err;
}
