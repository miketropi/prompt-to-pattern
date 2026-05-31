/**
 * Data store — manages prompt, patterns, compose state, preview.
 */
import { createReduxStore, register } from '@wordpress/data';

const DEFAULT_STATE = {
	prompt: '',
	target: 'page_content',
	allowGeneration: false,
	patterns: [],
	designTokens: {},
	isLoadingPatterns: false,
	isComposing: false,
	composeResult: null,
	error: null,
};

const actions = {
	setPrompt( prompt ) {
		return { type: 'SET_PROMPT', prompt };
	},
	setTarget( target ) {
		return { type: 'SET_TARGET', target };
	},
	setAllowGeneration( allowGeneration ) {
		return { type: 'SET_ALLOW_GENERATION', allowGeneration };
	},
	setPatterns( patterns ) {
		return { type: 'SET_PATTERNS', patterns };
	},
	setDesignTokens( designTokens ) {
		return { type: 'SET_DESIGN_TOKENS', designTokens };
	},
	setIsLoadingPatterns( isLoadingPatterns ) {
		return { type: 'SET_IS_LOADING_PATTERNS', isLoadingPatterns };
	},
	setIsComposing( isComposing ) {
		return { type: 'SET_IS_COMPOSING', isComposing };
	},
	setComposeResult( composeResult ) {
		return { type: 'SET_COMPOSE_RESULT', composeResult };
	},
	setError( error ) {
		return { type: 'SET_ERROR', error };
	},
	reset() {
		return { type: 'RESET' };
	},
};

const store = createReduxStore( 'prompt-to-pattern', {
	reducer( state = DEFAULT_STATE, action ) {
		switch ( action.type ) {
			case 'SET_PROMPT':
				return { ...state, prompt: action.prompt };
			case 'SET_TARGET':
				return { ...state, target: action.target };
			case 'SET_ALLOW_GENERATION':
				return { ...state, allowGeneration: action.allowGeneration };
			case 'SET_PATTERNS':
				return { ...state, patterns: action.patterns };
			case 'SET_DESIGN_TOKENS':
				return { ...state, designTokens: action.designTokens };
			case 'SET_IS_LOADING_PATTERNS':
				return {
					...state,
					isLoadingPatterns: action.isLoadingPatterns,
				};
			case 'SET_IS_COMPOSING':
				return { ...state, isComposing: action.isComposing };
			case 'SET_COMPOSE_RESULT':
				return { ...state, composeResult: action.composeResult };
			case 'SET_ERROR':
				return { ...state, error: action.error };
			case 'RESET':
				return {
					...DEFAULT_STATE,
					patterns: state.patterns,
					designTokens: state.designTokens,
				};
			default:
				return state;
		}
	},

	actions,

	selectors: {
		getPrompt( state ) {
			return state.prompt;
		},
		getTarget( state ) {
			return state.target;
		},
		getAllowGeneration( state ) {
			return state.allowGeneration;
		},
		getPatterns( state ) {
			return state.patterns;
		},
		getDesignTokens( state ) {
			return state.designTokens;
		},
		isLoadingPatterns( state ) {
			return state.isLoadingPatterns;
		},
		isComposing( state ) {
			return state.isComposing;
		},
		getComposeResult( state ) {
			return state.composeResult;
		},
		getError( state ) {
			return state.error;
		},
	},
} );

register( store );
