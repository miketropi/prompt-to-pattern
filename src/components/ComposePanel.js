/**
 * ComposePanel — main sidebar UI.
 *
 * Fetches patterns on mount, renders prompt input + controls,
 * and triggers compose → preview flow.
 */
import { useEffect, useCallback } from '@wordpress/element';
import { PluginSidebar } from '@wordpress/editor';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import PromptInput from './PromptInput';
import ModeToggle from './ModeToggle';
import TargetToggle from './TargetToggle';
import PreviewModal from './PreviewModal';
import { fetchPatterns, composePage } from '../api';

export default function ComposePanel() {
	const {
		prompt,
		target,
		allowGeneration,
		patterns,
		isLoadingPatterns,
		isComposing,
		error,
	} = useSelect( ( select ) => ( {
		prompt: select( 'prompt-to-pattern' ).getPrompt(),
		target: select( 'prompt-to-pattern' ).getTarget(),
		allowGeneration: select( 'prompt-to-pattern' ).getAllowGeneration(),
		patterns: select( 'prompt-to-pattern' ).getPatterns(),
		isLoadingPatterns: select( 'prompt-to-pattern' ).isLoadingPatterns(),
		isComposing: select( 'prompt-to-pattern' ).isComposing(),
		error: select( 'prompt-to-pattern' ).getError(),
	} ) );

	const {
		setPatterns,
		setDesignTokens,
		setIsLoadingPatterns,
		setIsComposing,
		setComposeResult,
		setError,
	} = useDispatch( 'prompt-to-pattern' );

	useEffect( () => {
		setIsLoadingPatterns( true );
		setError( null );
		fetchPatterns()
			.then( ( catalog ) => {
				setPatterns( catalog.patterns || [] );
				setDesignTokens( catalog.design_tokens || {} );
			} )
			.catch( ( err ) => {
				setError(
					err?.message ||
						__(
							'Could not load patterns. Please try again.',
							'prompt-to-pattern'
						)
				);
			} )
			.finally( () => {
				setIsLoadingPatterns( false );
			} );
	}, [ setPatterns, setDesignTokens, setIsLoadingPatterns, setError ] );

	const handleCompose = useCallback( () => {
		if ( ! prompt.trim() ) {
			return;
		}

		setIsComposing( true );
		setError( null );
		setComposeResult( null );

		composePage( { prompt, target, allowGeneration } )
			.then( ( result ) => {
				setComposeResult( result );
			} )
			.catch( ( err ) => {
				setError(
					err?.message ||
						__(
							'Compose failed. Please check your AI provider configuration.',
							'prompt-to-pattern'
						)
				);
			} )
			.finally( () => {
				setIsComposing( false );
			} );
	}, [
		prompt,
		target,
		allowGeneration,
		setIsComposing,
		setError,
		setComposeResult,
	] );

	const patternCount = patterns.length;

	return (
		<>
			<PluginSidebar
				name="prompt-to-pattern"
				title={ __( 'Prompt to Pattern', 'prompt-to-pattern' ) }
				icon="editor-help"
			>
				<PanelBody
					title={ __( 'Compose', 'prompt-to-pattern' ) }
					initialOpen
				>
					<PromptInput />

					<div style={ { marginTop: '1em' } }>
						<TargetToggle />
					</div>

					<div style={ { marginTop: '1em' } }>
						<ModeToggle />
					</div>

					{ isLoadingPatterns && (
						<div
							style={ {
								marginTop: '1em',
								display: 'flex',
								alignItems: 'center',
								gap: '0.5em',
							} }
						>
							<Spinner />
							<span>
								{ __(
									'Loading patterns…',
									'prompt-to-pattern'
								) }
							</span>
						</div>
					) }

					{ ! isLoadingPatterns && patternCount > 0 && (
						<p
							style={ {
								marginTop: '1em',
								color: '#757575',
								fontSize: '0.9em',
							} }
						>
							{ sprintf(
								/* translators: %d: number of available patterns */
								__(
									'%d patterns available',
									'prompt-to-pattern'
								),
								patternCount
							) }
						</p>
					) }

					{ error && ! isLoadingPatterns && (
						<Notice
							status="error"
							isDismissible
							onRemove={ () => setError( null ) }
							style={ { marginTop: '1em' } }
						>
							{ error }
						</Notice>
					) }

					{ ! isLoadingPatterns && patternCount === 0 && ! error && (
						<Notice
							status="warning"
							isDismissible={ false }
							style={ { marginTop: '1em' } }
						>
							{ __(
								'No block patterns found. Activate a block theme to enable pattern-based composition.',
								'prompt-to-pattern'
							) }
						</Notice>
					) }

					<div style={ { marginTop: '1.5em' } }>
						<Button
							variant="primary"
							onClick={ handleCompose }
							disabled={
								isComposing ||
								! prompt.trim() ||
								isLoadingPatterns
							}
							isBusy={ isComposing }
						>
							{ isComposing
								? __( 'Composing…', 'prompt-to-pattern' )
								: __( 'Compose', 'prompt-to-pattern' ) }
						</Button>
					</div>
				</PanelBody>

				{ patternCount > 0 && (
					<PanelBody
						title={ __(
							'Available Patterns',
							'prompt-to-pattern'
						) }
						initialOpen={ false }
					>
						<ul
							style={ {
								listStyle: 'none',
								margin: 0,
								padding: 0,
							} }
						>
							{ patterns.map( ( p ) => (
								<li
									key={ p.slug }
									style={ {
										padding: '0.5em 0',
										borderBottom: '1px solid #e0e0e0',
										fontSize: '0.85em',
									} }
								>
									<strong>{ p.title }</strong>
									<br />
									<small style={ { color: '#757575' } }>
										{ p.slug }
									</small>
								</li>
							) ) }
						</ul>
					</PanelBody>
				) }
			</PluginSidebar>

			<PreviewModal />
		</>
	);
}
