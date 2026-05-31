/**
 * API client — thin wrappers around apiFetch for Prompt to Pattern endpoints.
 */
import apiFetch from '@wordpress/api-fetch';

const API_NAMESPACE = 'prompt-to-pattern/v1';

/**
 * Fetch the pattern catalog from the REST endpoint.
 *
 * @return {Promise<{patterns: Array, design_tokens: Object}>} Catalog.
 */
export function fetchPatterns() {
	return apiFetch( { path: `/${ API_NAMESPACE }/patterns` } );
}

/**
 * Send a compose request to the AI agent.
 *
 * @param {Object}  params                         Compose parameters.
 * @param {string}  params.prompt                  Natural-language prompt.
 * @param {string}  [params.target='page_content'] Output target.
 * @param {boolean} [params.allowGeneration=false] Allow Mode B generation.
 * @return {Promise<Object>} Compose result.
 */
export function composePage( {
	prompt,
	target = 'page_content',
	allowGeneration = false,
} ) {
	return apiFetch( {
		path: `/${ API_NAMESPACE }/compose`,
		method: 'POST',
		data: {
			prompt,
			target,
			allow_generation: allowGeneration,
		},
	} );
}
