/**
 * Main admin application.
 */
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Dashboard from './screens/Dashboard';
import AssetsExplorer from './screens/AssetsExplorer';
import RulesList from './screens/RulesList';
import CreateRule from './screens/CreateRule';
import ScanHistory from './screens/ScanHistory';
import Settings from './screens/Settings';
import DebugLogs from './screens/DebugLogs';
import DependencyGraph from './screens/DependencyGraph';
import Recommendations from './screens/Recommendations';
import PageAnalyzer from './screens/PageAnalyzer';
import { isBulkRuleMode } from './bulkRuleSession';
import './style.scss';

const getPageFromHook = () => {
	const params = new URLSearchParams( window.location.search );
	return params.get( 'page' ) || 'assetpilot';
};

const getPreselectedAsset = () => {
	const params = new URLSearchParams( window.location.search );
	const handle = params.get( 'handle' );
	const type = params.get( 'type' );
	if ( handle && type ) {
		return { handle, type };
	}
	return null;
};

export default function App() {
	const page = useMemo( getPageFromHook, [] );
	const [ editRule, setEditRule ] = useState( null );
	const [ view, setView ] = useState( 'list' );

	const titles = {
		'assetpilot': __( 'Dashboard', 'assetpilot' ),
		'assetpilot-assets': __( 'Assets Explorer', 'assetpilot' ),
		'assetpilot-rules': __( 'Rules', 'assetpilot' ),
		'assetpilot-create': isBulkRuleMode()
			? __( 'Bulk Rule', 'assetpilot' )
			: __( 'Create Rule', 'assetpilot' ),
		'assetpilot-scan-history': __( 'Scan History', 'assetpilot' ),
		'assetpilot-analyzer': __( 'Page Analyzer', 'assetpilot' ),
		'assetpilot-recommendations': __( 'Recommendations', 'assetpilot' ),
		'assetpilot-graph': __( 'Dependency Graph', 'assetpilot' ),
		'assetpilot-logs': __( 'Debug Logs', 'assetpilot' ),
		'assetpilot-settings': __( 'Settings', 'assetpilot' ),
	};

	const preselected = useMemo( getPreselectedAsset, [] );

	const renderContent = () => {
		switch ( page ) {
			case 'assetpilot-assets':
				return <AssetsExplorer />;
			case 'assetpilot-rules':
				if ( view === 'edit' && editRule ) {
					return (
						<CreateRule
							editRule={ editRule }
							onSaved={ () => {
								setEditRule( null );
								setView( 'list' );
								const url = new URL( window.location.href );
								url.searchParams.set( 'assetpilot_saved', '1' );
								window.history.replaceState( {}, '', url.toString() );
							} }
						/>
					);
				}
				return (
					<RulesList
						onEdit={ ( rule ) => {
							setEditRule( rule );
							setView( 'edit' );
						} }
					/>
				);
			case 'assetpilot-create':
				return <CreateRule editRule={ editRule } preselected={ preselected } />;
			case 'assetpilot-analyzer':
				return <PageAnalyzer />;
			case 'assetpilot-scan-history':
				return <ScanHistory />;
			case 'assetpilot-recommendations':
				return <Recommendations />;
			case 'assetpilot-graph':
				return <DependencyGraph />;
			case 'assetpilot-logs':
				return <DebugLogs />;
			case 'assetpilot-settings':
				return <Settings />;
			default:
				return <Dashboard />;
		}
	};

	const isAssetsPage = page === 'assetpilot-assets';

	return (
		<div className={ `assetpilot-app${ isAssetsPage ? ' assetpilot-app--assets' : '' }` }>
			<h1 className="assetpilot-page-title">{ titles[ page ] || titles[ 'assetpilot' ] }</h1>
			{ renderContent() }
		</div>
	);
}
