/**
 * PreviewModal — displays compose result and offers approve/insert action.
 */
import { Modal, Button, Notice, PanelBody } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { parse } from '@wordpress/blocks';

export default function PreviewModal() {
	const composeResult = useSelect( ( select ) =>
		select( 'prompt-to-pattern' ).getComposeResult()
	);
	const error = useSelect( ( select ) =>
		select( 'prompt-to-pattern' ).getError()
	);
	const { setComposeResult, setError } = useDispatch( 'prompt-to-pattern' );
	const { resetBlocks } = useDispatch( 'core/block-editor' );

	if ( ! composeResult && ! error ) {
		return null;
	}

	const handleClose = () => {
		setComposeResult( null );
		setError( null );
	};

	const handleInsert = () => {
		if ( ! composeResult || ! composeResult.markup ) {
			return;
		}

		const blocks = parse( composeResult.markup );
		resetBlocks( blocks );

		handleClose();
	};

	const sections = composeResult?.sections || [];
	const unmet = composeResult?.unmet || [];
	const notes = composeResult?.notes || '';
	const generated = composeResult?.generated || 0;
	const target = composeResult?.target || 'page_content';

	return (
		<Modal
			title={ __( 'Compose Preview', 'prompt-to-pattern' ) }
			onRequestClose={ handleClose }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ composeResult && (
				<>
					{ notes && (
						<p style={ { color: '#757575', fontStyle: 'italic' } }>
							{ notes }
						</p>
					) }

					<PanelBody
						title={ sprintf(
							/* translators: %d: section count */
							__( 'Sections (%d)', 'prompt-to-pattern' ),
							sections.length
						) }
						initialOpen
					>
						<ul
							style={ {
								listStyle: 'none',
								margin: 0,
								padding: 0,
							} }
						>
							{ sections.map( ( section, index ) => (
								<li
									key={ index }
									style={ {
										padding: '0.3em 0',
										fontSize: '0.9em',
										borderBottom: '1px solid #f0f0f0',
									} }
								>
									{ section.generated ? (
										<>
											<small
												style={ {
													color: '#f0b849',
												} }
											>
												[gen]{ ' ' }
											</small>
											{ section.label || section.slug }
											{ ! section.valid && ' — skipped' }
										</>
									) : (
										<>{ section.title || section.slug }</>
									) }
								</li>
							) ) }
						</ul>
					</PanelBody>

					{ unmet.length > 0 && (
						<Notice
							status="warning"
							isDismissible={ false }
							style={ { marginTop: '1em' } }
						>
							{ sprintf(
								/* translators: %s: comma-separated unmet items */
								__(
									'Could not satisfy: %s',
									'prompt-to-pattern'
								),
								unmet.join( ', ' )
							) }
						</Notice>
					) }

					{ generated > 0 && (
						<Notice
							status="info"
							isDismissible={ false }
							style={ { marginTop: '1em' } }
						>
							{ sprintf(
								/* translators: %d: number of AI-generated sections */
								__(
									'%d AI-generated section(s) included.',
									'prompt-to-pattern'
								),
								generated
							) }
						</Notice>
					) }

					<p
						style={ {
							marginTop: '1em',
							fontSize: '0.85em',
							color: '#757575',
						} }
					>
						{ target === 'fse_template'
							? __(
									'Output wrapped as an FSE template.',
									'prompt-to-pattern'
							  )
							: __(
									'Content will replace the current page content.',
									'prompt-to-pattern'
							  ) }
					</p>
				</>
			) }

			<div
				style={ {
					marginTop: '1.5em',
					display: 'flex',
					justifyContent: 'flex-end',
					gap: '0.5em',
				} }
			>
				<Button variant="secondary" onClick={ handleClose }>
					{ __( 'Cancel', 'prompt-to-pattern' ) }
				</Button>
				{ composeResult && composeResult.markup && (
					<Button variant="primary" onClick={ handleInsert }>
						{ __( 'Insert into page', 'prompt-to-pattern' ) }
					</Button>
				) }
			</div>
		</Modal>
	);
}
