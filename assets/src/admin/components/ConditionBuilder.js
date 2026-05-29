/**
 * Elementor-style condition builder for Create Rule step 3.
 */
import { useState, useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ConditionRow from './ConditionRow';
import {
	conditionsToRows,
	rowsToConditions,
	createEmptyRow,
	createRowId,
} from './conditionUtils';

const getOptions = () => window.assetpilotAdmin?.conditionOptions || {};

/**
 * @param {Object} props
 * @param {Object} props.conditions
 * @param {(next: Object) => void} props.onChange
 */
export default function ConditionBuilder( { conditions, onChange, defaultScanUrl = '' } ) {
	const options = useMemo( () => getOptions(), [] );
	const [ rows, setRows ] = useState( () => conditionsToRows( conditions, options ) );

	const isEntireSite = rows.some(
		( row ) => row.scope === 'entire_site' && row.mode === 'include'
	);

	const pushConditions = ( nextRows ) => {
		setRows( nextRows );
		onChange( rowsToConditions( nextRows, options ) );
	};

	const updateRow = ( id, nextRow ) => {
		let nextRows = rows.map( ( row ) => ( row.id === id ? nextRow : row ) );

		if ( nextRow.scope === 'entire_site' && nextRow.mode === 'include' ) {
			nextRows = [ nextRow ];
		}

		pushConditions( nextRows );
	};

	const removeRow = ( id ) => {
		if ( rows.length <= 1 ) {
			return;
		}
		pushConditions( rows.filter( ( row ) => row.id !== id ) );
	};

	const addRow = () => {
		if ( isEntireSite ) {
			return;
		}
		pushConditions( [ ...rows, createEmptyRow() ] );
	};

	const setEntireSite = () => {
		pushConditions( [
			{
				id: createRowId(),
				mode: 'include',
				scope: 'entire_site',
				target: '',
				detail: '',
				postId: null,
				postLabel: '',
			},
		] );
	};

	const clearEntireSite = () => {
		pushConditions( [ createEmptyRow() ] );
	};

	return (
		<div className="assetpilot-condition-builder">
			<p className="assetpilot-condition-builder__intro">
				{ __(
					'Set where this rule applies. Add conditions below—matching any row in a group is enough; different groups must all match.',
					'assetpilot'
				) }
			</p>

			{ isEntireSite ? (
				<div className="assetpilot-condition-builder__entire">
					<span className="assetpilot-badge assetpilot-badge--plugin">
						{ __( 'Entire site', 'assetpilot' ) }
					</span>
					<Button variant="link" onClick={ clearEntireSite }>
						{ __( 'Add specific conditions instead', 'assetpilot' ) }
					</Button>
				</div>
			) : (
				<>
					<div className="assetpilot-condition-builder__rows">
						{ rows.map( ( row ) => (
							<ConditionRow
								key={ row.id }
								row={ row }
								defaultScanUrl={ defaultScanUrl }
								onChange={ ( next ) => updateRow( row.id, next ) }
								onRemove={ () => removeRow( row.id ) }
								canRemove={ rows.length > 1 }
							/>
						) ) }
					</div>

					<div className="assetpilot-condition-builder__actions">
						<Button variant="secondary" onClick={ addRow }>
							{ __( 'Add condition', 'assetpilot' ) }
						</Button>
						<Button variant="link" onClick={ setEntireSite }>
							{ __( 'Apply to entire site', 'assetpilot' ) }
						</Button>
					</div>
				</>
			) }
		</div>
	);
}
