/**
 * Interactive dependency graph (React Flow).
 */
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import {
	ReactFlow,
	ReactFlowProvider,
	Background,
	Controls,
	MiniMap,
	Panel,
	Handle,
	Position,
	useNodesState,
	useEdgesState,
	MarkerType,
} from '@xyflow/react';
import { Button, Spinner, Notice, TextControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchDependencyGraph } from '../api';
import '@xyflow/react/dist/style.css';

const NODE_WIDTH = 200;

function GraphNode( { data } ) {
	const rules = data.rules || [];
	return (
		<div
			className={ `assetpilot-graph-node${ data.is_critical ? ' assetpilot-graph-node--critical' : '' }${
				data.enqueued ? '' : ' assetpilot-graph-node--not-enqueued'
			}` }
			style={ { width: NODE_WIDTH } }
		>
			<Handle type="target" position={ Position.Left } className="assetpilot-graph-node__handle-port" />
			<Handle type="source" position={ Position.Right } className="assetpilot-graph-node__handle-port" />
			<div className="assetpilot-graph-node__type">{ data.type }</div>
			<div className="assetpilot-graph-node__handle">{ data.label }</div>
			{ data.is_critical && (
				<span className="assetpilot-graph-node__badge">{ __( 'Critical', 'assetpilot' ) }</span>
			) }
			{ rules.length > 0 && (
				<ul className="assetpilot-graph-node__rules">
					{ rules.map( ( rule ) => (
						<li
							key={ rule.id }
							className={ `assetpilot-graph-node__rule${ rule.enabled ? '' : ' is-disabled' }` }
						>
							{ rule.label || rule.action_type }
						</li>
					) ) }
				</ul>
			) }
			{ data.dependent_count > 0 && (
				<div className="assetpilot-graph-node__meta">
					{ sprintf(
						/* translators: %d: number of dependents */
						__( '%d dependents', 'assetpilot' ),
						data.dependent_count
					) }
				</div>
			) }
		</div>
	);
}

const nodeTypes = { assetpilotAsset: GraphNode };

function getInitialParams() {
	const params = new URLSearchParams( window.location.search );
	return {
		scanUrl: params.get( 'scan_url' ) || params.get( 'page_url' ) || window.assetpilotAdmin?.homeUrl || '/',
		focusHandle: params.get( 'handle' ) || '',
		focusType: params.get( 'type' ) || 'script',
	};
}

function collectDescendants( nodeId, edges, hidden ) {
	edges.forEach( ( edge ) => {
		if ( edge.source === nodeId && ! hidden.has( edge.target ) ) {
			hidden.add( edge.target );
			collectDescendants( edge.target, edges, hidden );
		}
	} );
}

export default function DependencyGraph() {
	const initial = useMemo( getInitialParams, [] );
	const [ scanUrl, setScanUrl ] = useState( initial.scanUrl );
	const [ assetType, setAssetType ] = useState( 'all' );
	const [ focusHandle, setFocusHandle ] = useState( initial.focusHandle );
	const [ focusType, setFocusType ] = useState( initial.focusType );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ meta, setMeta ] = useState( null );
	const [ collapsed, setCollapsed ] = useState( () => new Set() );

	const [ nodes, setNodes, onNodesChange ] = useNodesState( [] );
	const [ edges, setEdges, onEdgesChange ] = useEdgesState( [] );
	const [ rawEdges, setRawEdges ] = useState( [] );

	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		fetchDependencyGraph( {
			scan_url: scanUrl,
			asset_type: assetType,
			focus_handle: focusHandle || undefined,
			focus_type: focusType,
		} )
			.then( ( res ) => {
				setMeta( res.meta || null );
				setRawEdges( res.edges || [] );

				const flowNodes = ( res.nodes || [] ).map( ( node ) => ( {
					id: node.id,
					type: 'assetpilotAsset',
					position: node.position || { x: 0, y: 0 },
					style: { width: NODE_WIDTH },
					data: {
						...node,
						label: node.label || node.handle,
					},
				} ) );

				const flowEdges = ( res.edges || [] ).map( ( edge ) => {
					const stroke = edge.is_critical ? '#d63638' : '#50575e';
					return {
						id: edge.id,
						source: edge.source,
						target: edge.target,
						type: 'smoothstep',
						style: {
							stroke,
							strokeWidth: 2,
						},
						markerEnd: {
							type: MarkerType.ArrowClosed,
							color: stroke,
							width: 18,
							height: 18,
						},
						animated: !! edge.is_critical,
					};
				} );

				setNodes( flowNodes );
				setEdges( flowEdges );
				setCollapsed( new Set() );
			} )
			.catch( ( err ) => {
				setError( err?.message || __( 'Failed to load dependency graph.', 'assetpilot' ) );
				setNodes( [] );
				setEdges( [] );
				setRawEdges( [] );
			} )
			.finally( () => setLoading( false ) );
	}, [ scanUrl, assetType, focusHandle, focusType, setNodes, setEdges ] );

	useEffect( () => {
		load();
	}, [] );

	const applyCollapse = useCallback(
		( nextCollapsed ) => {
			const hidden = new Set();
			nextCollapsed.forEach( ( nodeId ) => collectDescendants( nodeId, rawEdges, hidden ) );

			setNodes( ( prev ) =>
				prev.map( ( node ) => ( {
					...node,
					hidden: hidden.has( node.id ),
				} ) )
			);
			setEdges( ( prev ) =>
				prev.map( ( edge ) => ( {
					...edge,
					hidden: hidden.has( edge.source ) || hidden.has( edge.target ),
				} ) )
			);
		},
		[ rawEdges, setNodes, setEdges ]
	);

	const onNodeClick = ( _, node ) => {
		const next = new Set( collapsed );
		if ( next.has( node.id ) ) {
			next.delete( node.id );
		} else {
			next.add( node.id );
		}
		setCollapsed( next );
		applyCollapse( next );
	};

	const assetsUrl = window.assetpilotAdmin?.assetsPageUrl || 'admin.php?page=assetpilot-assets';

	return (
		<div className="assetpilot-dependency-graph">
			<div className="assetpilot-dependency-graph__toolbar">
				<div className="assetpilot-dependency-graph__toolbar-row assetpilot-dependency-graph__toolbar-row--primary">
					<div className="assetpilot-dependency-graph__field assetpilot-dependency-graph__field--grow">
						<TextControl
							label={ __( 'Page URL', 'assetpilot' ) }
							value={ scanUrl }
							onChange={ setScanUrl }
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="assetpilot-dependency-graph__field assetpilot-dependency-graph__field--action">
						<Button variant="primary" onClick={ load } disabled={ loading }>
							{ loading ? <Spinner /> : __( 'Build graph', 'assetpilot' ) }
						</Button>
					</div>
				</div>
				<div className="assetpilot-dependency-graph__toolbar-row">
					<div className="assetpilot-dependency-graph__field">
						<SelectControl
							label={ __( 'Asset type', 'assetpilot' ) }
							value={ assetType }
							options={ [
								{ label: __( 'Scripts & styles', 'assetpilot' ), value: 'all' },
								{ label: __( 'Scripts only', 'assetpilot' ), value: 'script' },
								{ label: __( 'Styles only', 'assetpilot' ), value: 'style' },
							] }
							onChange={ setAssetType }
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="assetpilot-dependency-graph__field">
						<TextControl
							label={ __( 'Focus handle', 'assetpilot' ) }
							value={ focusHandle }
							onChange={ setFocusHandle }
							placeholder={ __( 'e.g. hello-theme-frontend', 'assetpilot' ) }
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="assetpilot-dependency-graph__field">
						<SelectControl
							label={ __( 'Focus type', 'assetpilot' ) }
							value={ focusType }
							options={ [
								{ label: __( 'Script', 'assetpilot' ), value: 'script' },
								{ label: __( 'Style', 'assetpilot' ), value: 'style' },
							] }
							onChange={ setFocusType }
							disabled={ ! focusHandle }
							__nextHasNoMarginBottom
						/>
					</div>
				</div>
			</div>

			<p className="description assetpilot-dependency-graph__hint">
				{ __(
					'Visitor-facing assets for this URL (admin bar excluded). Arrows run dependency → dependent. Dashed nodes are deps-only. Click a node to collapse dependents. Optional focus handle narrows the graph to one asset chain.',
					'assetpilot'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ meta?.truncated && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: max node count */
						__( 'Graph truncated to %d nodes for performance.', 'assetpilot' ),
						meta.max_nodes || 200
					) }
				</Notice>
			) }

			<div className="assetpilot-dependency-graph__canvas">
				{ loading && nodes.length === 0 ? (
					<div className="assetpilot-dependency-graph__loading">
						<Spinner />
					</div>
				) : (
					<ReactFlowProvider>
						<ReactFlow
							nodes={ nodes }
							edges={ edges }
							onNodesChange={ onNodesChange }
							onEdgesChange={ onEdgesChange }
							onNodeClick={ onNodeClick }
							nodeTypes={ nodeTypes }
							fitView
							fitViewOptions={ { padding: 0.2 } }
							minZoom={ 0.15 }
							maxZoom={ 1.5 }
							proOptions={ { hideAttribution: true } }
							defaultEdgeOptions={ {
								type: 'smoothstep',
								style: { stroke: '#50575e', strokeWidth: 2 },
								markerEnd: {
									type: MarkerType.ArrowClosed,
									color: '#50575e',
									width: 18,
									height: 18,
								},
							} }
						>
							<Background gap={ 16 } />
							<Controls />
							<MiniMap pannable zoomable />
							<Panel position="top-right">
								{ meta && (
									<span className="assetpilot-dependency-graph__stats">
										{ sprintf(
											/* translators: 1: nodes, 2: edges */
											__( '%1$d nodes · %2$d edges', 'assetpilot' ),
											meta.node_count || nodes.length,
											meta.edge_count || edges.length
										) }
									</span>
								) }
							</Panel>
						</ReactFlow>
					</ReactFlowProvider>
				) }
			</div>

			{ ! loading && nodes.length === 0 && ! error && (
				<p>{ __( 'No dependency data for this URL. Try scanning the page in Assets Explorer first.', 'assetpilot' ) }</p>
			) }

			<p className="description">
				<a href={ `${ assetsUrl }${ assetsUrl.includes( '?' ) ? '&' : '?' }scan_url=${ encodeURIComponent( scanUrl ) }` }>
					{ __( 'Open in Assets Explorer', 'assetpilot' ) }
				</a>
			</p>
		</div>
	);
}
