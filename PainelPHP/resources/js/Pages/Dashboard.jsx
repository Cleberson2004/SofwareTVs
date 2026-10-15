import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const statusTone = {
    online: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    offline: 'bg-slate-100 text-slate-600 ring-slate-200',
    queued: 'bg-amber-50 text-amber-700 ring-amber-200',
    leased: 'bg-sky-50 text-sky-700 ring-sky-200',
    running: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    succeeded: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    failed: 'bg-rose-50 text-rose-700 ring-rose-200',
};

const statusLabels = {
    online: 'Conectado',
    offline: 'Desconectado',
    queued: 'Na fila',
    leased: 'Reservado',
    running: 'Executando',
    succeeded: 'Concluído',
    failed: 'Falhou',
};

const adjustmentLabels = {
    EDGE_ADJUST: 'Ajustar bordas',
    FIT: 'Encaixar',
    FILL: 'Preencher',
    STRETCH: 'Esticar',
    COMPOSITE_FILL: 'Composição final',
};

const MAX_TVS = 64;
const BASE_LONG = 1920;
const BASE_SHORT = 1080;

/** Monta o layout do videowall a partir da quantidade de TVs e da orientação. */
function buildWall(profile, tvCount, orientation) {
    const baseW = Number(profile?.display_width) || BASE_LONG;
    const baseH = Number(profile?.display_height) || BASE_SHORT;
    const long = Math.max(baseW, baseH);
    const short = Math.min(baseW, baseH);

    const display_width = orientation === 'portrait' ? short : long;
    const display_height = orientation === 'portrait' ? long : short;
    const count = Math.min(MAX_TVS, Math.max(1, Math.floor(Number(tvCount)) || 1));
    const profileRows = Math.max(1, Number(profile?.rows) || 1);
    const profileColumns = Math.max(1, Number(profile?.columns) || count);
    const usesProfileGrid = profileRows * profileColumns === count;
    const columns = usesProfileGrid ? profileColumns : count;
    const rows = usesProfileGrid ? profileRows : 1;

    return {
        id: profile?.id,
        name: profile?.name,
        orientation,
        tv_count: count,
        columns,
        rows,
        display_width,
        display_height,
        total_width: columns * display_width,
        total_height: rows * display_height,
    };
}

/** Compara a imagem com a resolução esperada e só acusa problema quando realmente não bate. */
function diagnose(asset, expectedWidth, expectedHeight) {
    if (!asset || !expectedWidth || !expectedHeight) return null;

    const { original_width: w, original_height: h } = asset;

    if (w === expectedWidth && h === expectedHeight) {
        return { ok: true, label: 'Confere', message: null };
    }

    if (w === expectedHeight && h === expectedWidth) {
        return { ok: false, label: 'Orientação invertida', message: `A imagem (${w} × ${h}) está com a orientação oposta à esperada (${expectedWidth} × ${expectedHeight}).` };
    }

    const sameRatio = Math.abs(w / h - expectedWidth / expectedHeight) / (expectedWidth / expectedHeight) < 0.005;

    if (sameRatio) {
        return { ok: false, label: 'Proporção ok, tamanho diferente', message: `A proporção está correta, mas a imagem tem ${w} × ${h} e o esperado é ${expectedWidth} × ${expectedHeight}. Use um ajuste para normalizar.` };
    }

    return { ok: false, label: 'Não confere', message: `A imagem tem ${w} × ${h} e o esperado é ${expectedWidth} × ${expectedHeight}. Use um ajuste para normalizar.` };
}

function describeScreenIssue(asset, expectedWidth, expectedHeight, screen) {
    if (!asset || (asset.original_width === expectedWidth && asset.original_height === expectedHeight)) return null;

    if (asset.original_width === expectedHeight && asset.original_height === expectedWidth) {
        return `TV ${screen}: orientação invertida`;
    }

    const differences = [
        asset.original_width === expectedWidth
            ? null
            : `largura ${Math.abs(asset.original_width - expectedWidth)} px ${asset.original_width < expectedWidth ? 'menor' : 'maior'}`,
        asset.original_height === expectedHeight
            ? null
            : `altura ${Math.abs(asset.original_height - expectedHeight)} px ${asset.original_height < expectedHeight ? 'menor' : 'maior'}`,
    ].filter(Boolean);

    return `TV ${screen}: ${differences.join(' · ')}`;
}

function screenPresentation(asset, profile, comparison, preferredVariantId = null) {
    if (!asset) return null;

    const variant = comparison === 'normalized'
        ? asset.variants.find((item) => item.id === preferredVariantId
            && item.variant_type === 'screen'
            && Number(item.width) === profile.display_width
            && Number(item.height) === profile.display_height)
            ?? asset.variants.find((item) => item.variant_type === 'screen'
                && Number(item.width) === profile.display_width
                && Number(item.height) === profile.display_height)
        : null;

    return variant
        ? { url: variant.preview_url, width: variant.width, height: variant.height, corrected: true, variant }
        : { url: asset.original_url, width: asset.original_width, height: asset.original_height, corrected: false, variant: null };
}

function ScreenPreviewImage({ presentation, profile, alt, draggable = false }) {
    if (!presentation) return null;

    const style = presentation.corrected ? undefined : {
        left: '50%',
        top: '50%',
        width: `${(presentation.width / profile.display_width) * 100}%`,
        height: `${(presentation.height / profile.display_height) * 100}%`,
        maxWidth: 'none',
        transform: 'translate(-50%, -50%)',
    };

    return <img src={presentation.url} draggable={draggable} className={`absolute object-fill ${presentation.corrected ? 'inset-0 size-full' : ''}`} style={style} alt={alt} />;
}

function Badge({ value }) {
    return <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider ring-1 ${statusTone[value] ?? statusTone.offline}`}>{statusLabels[value] ?? value}</span>;
}

function Icon({ name, className = 'size-5' }) {
    const paths = {
        wall: <><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M9 4v14M15 4v14"/></>,
        upload: <><path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></>,
        monitor: <><rect x="3" y="3" width="18" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/></>,
        activity: <path d="M3 12h4l2-7 4 14 2-7h6"/>,
        check: <path d="m5 12 4 4L19 6"/>,
        zoom: <><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></>,
    };

    return <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name]}</svg>;
}

function VideoWall({ profile, imageUrl, zoom, activeScreen, assignments, assets, comparison, selectedVariantId, compositionMode, onAssign, onClear, onMove, onScreenSelect }) {
    if (!profile) {
        return <div className="grid min-h-72 place-items-center text-sm text-slate-500">Cadastre um perfil de tela para iniciar.</div>;
    }

    const screens = Array.from({ length: profile.rows * profile.columns });
    const hasAssignments = Object.keys(assignments).length > 0;
    const assignedAsset = activeScreen === null ? null : assets.find((asset) => asset.id === assignments[activeScreen]);
    const assignedPresentation = screenPresentation(assignedAsset, profile, comparison, selectedVariantId);
    const assignedIssue = assignedPresentation?.corrected ? null : describeScreenIssue(assignedAsset, profile.display_width, profile.display_height, activeScreen);
    const style = activeScreen === null
        ? { aspectRatio: `${profile.total_width}/${profile.total_height}`, transform: `scale(${zoom})` }
        : { aspectRatio: `${profile.display_width}/${profile.display_height}`, transform: `scale(${zoom})` };

    function dropOnScreen(event, targetScreen) {
        event.preventDefault();

        const sourceScreen = Number(event.dataTransfer.getData('application/x-tv-wall-screen'));
        if (sourceScreen && sourceScreen !== targetScreen) {
            onMove(sourceScreen, targetScreen);
            return;
        }

        const assetId = Number(event.dataTransfer.getData('text/plain'));
        if (assetId) onAssign(targetScreen, assetId);
    }

    return (
        <div className="grid min-h-72 place-items-center overflow-auto bg-[#e9edf3] p-7 shadow-inner shadow-slate-300/60">
            <div
                className={`relative w-full origin-center overflow-hidden rounded-lg bg-black transition-transform duration-200 ${assignedIssue ? 'ring-2 ring-rose-500' : 'ring-1 ring-white/15'}`}
                style={style}
                onDragOver={(event) => activeScreen !== null && event.preventDefault()}
                onDrop={(event) => {
                    if (activeScreen === null) return;
                    dropOnScreen(event, activeScreen);
                }}
            >
                {activeScreen !== null && hasAssignments ? (
                    assignedAsset
                        ? <>
                            <ScreenPreviewImage presentation={assignedPresentation} profile={profile} alt={`Conteúdo da TV ${activeScreen}`} />
                            <button type="button" className="absolute right-3 top-3 z-30 grid size-8 place-items-center rounded-full border border-rose-300/50 bg-rose-950/90 text-base font-black text-rose-100 shadow-lg backdrop-blur transition hover:bg-rose-500 hover:text-white" onClick={() => onClear(activeScreen)} aria-label={`Remover imagem da TV ${activeScreen}`} title="Remover da composição">×</button>
                            {assignedIssue && <div className="absolute left-1/2 top-4 z-20 max-w-[90%] -translate-x-1/2 rounded-lg border border-rose-300/60 bg-rose-950/95 px-3 py-2 text-center text-xs font-bold text-rose-100 shadow-xl shadow-black/50 backdrop-blur">{assignedIssue}</div>}
                        </>
                        : <div className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_50%_20%,#16253e,#080d17_60%)] text-xs font-bold uppercase tracking-widest text-slate-500">Arraste uma imagem para a TV {activeScreen}</div>
                ) : imageUrl && !hasAssignments && compositionMode === 'wall' ? (
                    <img
                        src={imageUrl}
                        className="absolute inset-0 size-full object-fill"
                        style={activeScreen === null ? undefined : {
                            width: `${profile.columns * 100}%`,
                            height: `${profile.rows * 100}%`,
                            maxWidth: 'none',
                            transform: `translate(-${((activeScreen - 1) % profile.columns) * (100 / profile.columns)}%, -${Math.floor((activeScreen - 1) / profile.columns) * (100 / profile.rows)}%)`,
                            transformOrigin: 'top left',
                        }}
                        alt="Prévia da campanha"
                    />
                ) : <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_20%,#16253e,#080d17_60%)]" />}
                {activeScreen === null && (
                    <div className="absolute inset-0 grid" style={{ gridTemplateColumns: `repeat(${profile.columns}, 1fr)`, gridTemplateRows: `repeat(${profile.rows}, 1fr)` }}>
                        {screens.map((_, index) => {
                            const screen = index + 1;
                            const asset = assets.find((item) => item.id === assignments[screen]);
                            const presentation = screenPresentation(asset, profile, comparison, selectedVariantId);
                            const issue = presentation?.corrected ? null : describeScreenIssue(asset, profile.display_width, profile.display_height, screen);

                            return (
                            <div
                                key={index}
                                draggable={Boolean(asset)}
                                title={asset ? `Arraste a imagem da TV ${screen} para mover ou trocar de posição` : `Solte uma imagem na TV ${screen}`}
                                className={`relative overflow-hidden ${compositionMode === 'wall' ? 'bg-transparent' : 'bg-black'} shadow-[inset_0_0_0_1px_rgba(255,255,255,.32)] transition hover:bg-indigo-300/10 ${asset ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer'} ${issue ? 'z-10 border-2 border-rose-500 ring-2 ring-inset ring-rose-500/60' : 'border border-slate-400/70 hover:ring-2 hover:ring-inset hover:ring-indigo-300'}`}
                                onClick={() => onScreenSelect(screen)}
                                onDragStart={(event) => {
                                    if (!asset) return;
                                    event.dataTransfer.effectAllowed = 'move';
                                    event.dataTransfer.setData('application/x-tv-wall-screen', String(screen));
                                    event.dataTransfer.setData('text/plain', String(asset.id));
                                }}
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={(event) => dropOnScreen(event, screen)}
                            >
                                <ScreenPreviewImage presentation={presentation} profile={profile} alt={asset?.name ?? ''} />
                                {asset && <button type="button" draggable={false} className="absolute right-1 top-1 z-30 grid size-6 place-items-center rounded-full border border-rose-300/50 bg-rose-950/90 text-xs font-black text-rose-100 shadow-lg backdrop-blur transition hover:bg-rose-500 hover:text-white" onMouseDown={(event) => event.stopPropagation()} onClick={(event) => { event.stopPropagation(); onClear(screen); }} aria-label={`Remover imagem da TV ${screen}`} title="Remover da composição">×</button>}
                                {issue && <div className="absolute inset-x-1 top-1 z-20 rounded-md border border-rose-300/60 bg-rose-950/95 px-1.5 py-1 text-center text-[9px] font-bold leading-tight text-rose-100 shadow-lg shadow-black/50 backdrop-blur" title={issue}>{issue}</div>}
                                <span className="absolute bottom-2 left-2 rounded-md bg-slate-950/80 px-2 py-1 text-[10px] font-extrabold tracking-widest text-slate-100 backdrop-blur">TV {index + 1}</span>
                                {!asset && hasAssignments && <span className="absolute inset-0 grid place-items-center text-[10px] font-bold uppercase tracking-wider text-slate-600">Solte aqui</span>}
                            </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Dashboard({ profiles, devices, assets, commands, flash, auth }) {
    usePoll(10000, { only: ['devices', 'commands'] });
    const initialProfile = profiles.find((profile) => profile.id === devices[0]?.current_display_profile_id) ?? profiles[0];
    const [selectedAssetId, setSelectedAssetId] = useState(assets[0]?.id ?? null);
    const [selectedVariantId, setSelectedVariantId] = useState(null);
    const [comparison, setComparison] = useState('original');
    const [zoom, setZoom] = useState(1);
    const [activeScreen, setActiveScreen] = useState(null);
    const [fullscreenScreen, setFullscreenScreen] = useState(null);
    const [screenAssignments, setScreenAssignments] = useState({});
    const [compositionMode, setCompositionMode] = useState('screens');
    const [mediaDialogOpen, setMediaDialogOpen] = useState(false);
    const [deviceId, setDeviceId] = useState(devices[0]?.id ?? '');
    const [campaignName, setCampaignName] = useState('Campanha principal');
    const [isConverting, setIsConverting] = useState(false);
    const [conversionError, setConversionError] = useState('');
    const [tvCount, setTvCount] = useState(initialProfile ? Math.max(1, initialProfile.rows * initialProfile.columns) : 4);
    const [orientation, setOrientation] = useState(initialProfile && initialProfile.display_height > initialProfile.display_width ? 'portrait' : 'landscape');
    const upload = useForm({ name: '', display_profile_id: initialProfile?.id ?? '', target_scope: 'screen', files: [] });
    const selectedAsset = assets.find((asset) => asset.id === selectedAssetId) ?? assets[0];
    const selectedVariant = selectedAsset?.variants.find((variant) => variant.id === selectedVariantId) ?? selectedAsset?.variants[0];
    const baseProfile = profiles.find((profile) => profile.id === Number(upload.data.display_profile_id)) ?? profiles[0];
    const wall = useMemo(() => buildWall(baseProfile, tvCount, orientation), [baseProfile, tvCount, orientation]);
    const assignedTvCount = Object.entries(screenAssignments)
        .filter(([screen, assetId]) => Number(screen) <= wall.tv_count && Boolean(assetId))
        .length;
    const isScreenCompositionValidation = compositionMode === 'screens' && assignedTvCount > 0;
    const validationScope = isScreenCompositionValidation ? 'composition' : upload.data.target_scope;
    const validationTvCount = compositionMode === 'wall' ? wall.tv_count : assignedTvCount;
    const isWallValidationPending = validationScope === 'wall' && validationTvCount === 0;
    const targetWidth = validationScope === 'screen' ? wall.display_width : wall.total_width;
    const targetHeight = validationScope === 'screen' ? wall.display_height : wall.total_height;
    const compositionIssues = isScreenCompositionValidation
        ? Array.from({ length: wall.tv_count }, (_, index) => {
            const screen = index + 1;
            const asset = assets.find((item) => item.id === screenAssignments[screen]);

            if (!asset) return `TV ${screen}: sem imagem`;

            const presentation = screenPresentation(asset, wall, comparison, selectedVariantId);
            return describeScreenIssue({
                original_width: Number(presentation.width),
                original_height: Number(presentation.height),
            }, wall.display_width, wall.display_height, screen);
        }).filter(Boolean)
        : [];
    const validationVariant = !isScreenCompositionValidation && comparison === 'normalized'
        ? selectedAsset?.variants.find((variant) => Number(variant.width) === targetWidth && Number(variant.height) === targetHeight)
        : null;
    const validationSource = isScreenCompositionValidation
        ? { original_width: wall.total_width, original_height: wall.total_height }
        : validationVariant
        ? { original_width: Number(validationVariant.width), original_height: Number(validationVariant.height) }
        : selectedAsset;
    const receivedResolution = isScreenCompositionValidation && assignedTvCount < wall.tv_count
        ? `${assignedTvCount} de ${wall.tv_count} TVs preenchidas`
        : `${validationSource?.original_width ?? '—'} × ${validationSource?.original_height ?? '—'}`;
    const diagnosis = useMemo(() => {
        if (isScreenCompositionValidation) {
            if (compositionIssues.length > 0) {
                return {
                    ok: false,
                    label: assignedTvCount < wall.tv_count ? 'Composição incompleta' : 'TVs com problema',
                    message: compositionIssues.join(' · '),
                };
            }

            return { ok: true, label: 'Confere', message: null };
        }

        if (isWallValidationPending) {
            return {
                ok: false,
                label: 'Aguardando TVs',
                message: 'Adicione uma imagem a pelo menos uma TV para calcular e validar a resolução do painel.',
            };
        }

        return diagnose(validationSource, targetWidth, targetHeight);
    }, [validationSource, targetWidth, targetHeight, isWallValidationPending, isScreenCompositionValidation, compositionIssues, assignedTvCount, wall.tv_count]);
    const imageUrl = comparison === 'original' ? selectedAsset?.original_url : (selectedVariant?.preview_url ?? selectedAsset?.original_url);
    const screens = useMemo(() => Array.from({ length: wall.rows * wall.columns }), [wall.rows, wall.columns]);
    const compositionScreens = useMemo(() => Object.keys(screenAssignments)
        .map(Number)
        .filter((screen) => screen >= 1 && screen <= wall.tv_count && assets.some((asset) => asset.id === screenAssignments[screen]))
        .sort((a, b) => a - b), [screenAssignments, wall.tv_count, assets]);
    const fullscreenScreens = compositionScreens.length > 0
        ? compositionScreens
        : screens.map((_, index) => index + 1);
    const fullscreenIsWall = compositionMode === 'wall' && Boolean(selectedAsset);
    const fullscreenAsset = fullscreenScreen === null
        ? null
        : (fullscreenIsWall ? selectedAsset : assets.find((asset) => asset.id === screenAssignments[fullscreenScreen]));
    const fullscreenPresentation = screenPresentation(fullscreenAsset, wall, comparison, selectedVariantId);
    const fullscreenIssue = fullscreenIsWall || fullscreenPresentation?.corrected
        ? null
        : describeScreenIssue(fullscreenAsset, wall.display_width, wall.display_height, fullscreenScreen);

    // Enviado ao backend junto com upload, normalização e publicação.
    const wallPayload = {
        tv_count: wall.tv_count,
        orientation: wall.orientation,
        rows: wall.rows,
        columns: wall.columns,
        display_width: wall.display_width,
        display_height: wall.display_height,
        total_width: wall.total_width,
        total_height: wall.total_height,
    };

    function changeTvCount(value) {
        const next = Math.min(MAX_TVS, Math.max(1, Math.floor(Number(value)) || 1));

        setTvCount(next);
        setScreenAssignments((current) => Object.fromEntries(Object.entries(current).filter(([screen]) => Number(screen) <= next)));
        setActiveScreen((current) => (current !== null && current > next ? null : current));
    }

    function moveFullscreen(direction) {
        setFullscreenScreen((current) => {
            const currentIndex = fullscreenScreens.indexOf(current);
            const startIndex = currentIndex === -1 ? 0 : currentIndex;
            return fullscreenScreens[(startIndex + direction + fullscreenScreens.length) % fullscreenScreens.length];
        });
    }

    function openFullscreenPreview() {
        setFullscreenScreen(fullscreenScreens.includes(activeScreen) ? activeScreen : fullscreenScreens[0]);
        document.documentElement.requestFullscreen?.().catch(() => {});
    }

    function closeFullscreenPreview() {
        setFullscreenScreen(null);
        if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
    }

    useEffect(() => {
        if (fullscreenScreen === null) return undefined;

        const previousDocumentOverflow = document.documentElement.style.overflow;
        const previousBodyOverflow = document.body.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        const handleKeyDown = (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                moveFullscreen(-1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                moveFullscreen(1);
            } else if (event.key === 'Escape') {
                closeFullscreenPreview();
            }
        };

        const handleFullscreenChange = () => {
            if (!document.fullscreenElement) setFullscreenScreen(null);
        };

        window.addEventListener('keydown', handleKeyDown);
        document.addEventListener('fullscreenchange', handleFullscreenChange);

        return () => {
            document.documentElement.style.overflow = previousDocumentOverflow;
            document.body.style.overflow = previousBodyOverflow;
            window.removeEventListener('keydown', handleKeyDown);
            document.removeEventListener('fullscreenchange', handleFullscreenChange);
        };
    }, [fullscreenScreen, fullscreenScreens]);

    function changeProfile(profileId) {
        const profile = profiles.find((item) => item.id === Number(profileId));

        upload.setData('display_profile_id', profileId);
        setScreenAssignments({});
        setActiveScreen(null);

        if (profile) {
            setTvCount(Math.max(1, profile.rows * profile.columns));
            setOrientation(profile.display_height > profile.display_width ? 'portrait' : 'landscape');
        }
    }

    function openMediaDialog() {
        upload.reset('name', 'files');
        upload.clearErrors();
        setConversionError('');
        setMediaDialogOpen(true);
    }

    function submitUpload(event) {
        event.preventDefault();
        upload.transform((data) => ({ ...data, ...wallPayload }));
        upload.post('/media', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                upload.reset('name', 'files');
                setMediaDialogOpen(false);
                if (page.props.flash?.profile_id) upload.setData('display_profile_id', page.props.flash.profile_id);
            },
        });
    }

    async function selectUploadFiles(event) {
        const selectedFiles = Array.from(event.target.files);
        setConversionError('');
        setIsConverting(true);

        try {
            const convertedFiles = await Promise.all(selectedFiles.map(async (file) => {
                const header = new Uint8Array(await file.slice(0, 24).arrayBuffer());
                const brand = new TextDecoder('ascii').decode(header.slice(4, 16));
                const isHeic = /\.(heic|heif)$/i.test(file.name) || ['ftypheic', 'ftypheif', 'ftyphevc', 'ftypmif1'].some((signature) => brand.includes(signature));

                if (!isHeic) return file;

                const { default: heic2any } = await import('heic2any');
                const result = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.92 });
                const jpeg = Array.isArray(result) ? result[0] : result;
                const name = file.name.replace(/\.(heic|heif|jpe?g)$/i, '') || 'imagem';

                return new File([jpeg], `${name}.jpg`, { type: 'image/jpeg', lastModified: file.lastModified });
            }));

            upload.setData('files', convertedFiles);
        } catch {
            upload.setData('files', []);
            setConversionError('Não foi possível converter uma imagem HEIC/HEIF. Tente exportá-la como JPEG ou PNG.');
        } finally {
            setIsConverting(false);
            event.target.value = '';
        }
    }

    function assignToScreen(screen, assetId = selectedAsset?.id) {
        if (!assetId) return;
        setCompositionMode('screens');
        setScreenAssignments((current) => ({ ...current, [screen]: assetId }));
        setSelectedAssetId(assetId);
        setSelectedVariantId(null);
    }

    function selectScreen(screen) {
        setActiveScreen(screen);

        const assetId = screenAssignments[screen];
        if (assetId) {
            setSelectedAssetId(assetId);
            setSelectedVariantId(null);
        }
    }

    function moveScreenAssignment(sourceScreen, targetScreen) {
        if (sourceScreen === targetScreen) return;

        setCompositionMode('screens');
        setScreenAssignments((current) => {
            const sourceAssetId = current[sourceScreen];
            if (!sourceAssetId) return current;

            const targetAssetId = current[targetScreen];
            const next = { ...current, [targetScreen]: sourceAssetId };

            if (targetAssetId) next[sourceScreen] = targetAssetId;
            else delete next[sourceScreen];

            return next;
        });
    }

    function clearScreen(screen) {
        setCompositionMode('screens');
        setScreenAssignments((current) => {
            const next = { ...current };
            delete next[screen];

            return next;
        });
    }

    function startNewComposition() {
        setCompositionMode('screens');
        setScreenAssignments({});
        setActiveScreen(null);
        setFullscreenScreen(null);
        setComparison('original');
        setSelectedVariantId(null);
    }

    function deleteAsset(asset) {
        if (!window.confirm(`Apagar “${asset.name}” e todas as suas variantes?`)) return;

        router.delete(`/media/${asset.id}`, {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.flash?.error) return;

                setScreenAssignments((current) => Object.fromEntries(
                    Object.entries(current).filter(([, assetId]) => assetId !== asset.id),
                ));

                if (selectedAssetId === asset.id) {
                    setSelectedAssetId(null);
                    setSelectedVariantId(null);
                }
            },
        });
    }

    function replicateSelectedImage() {
        if (!selectedAsset) return;

        setCompositionMode('screens');
        setScreenAssignments(Object.fromEntries(screens.map((_, index) => [index + 1, selectedAsset.id])));
        setActiveScreen(null);
    }

    function fillWallWithSelectedImage() {
        if (!selectedAsset || !baseProfile) return;

        setCompositionMode('wall');
        setScreenAssignments({});
        setActiveScreen(null);
        setComparison('normalized');
        setSelectedVariantId(null);
        router.post(`/media/${selectedAsset.id}/normalize`, {
            display_profile_id: baseProfile.id,
            adjustment_mode: 'FILL',
            target_scope: 'wall',
            ...wallPayload,
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const variantId = Number(page.props.flash?.variant_id);
                if (variantId) setSelectedVariantId(variantId);
                setComparison('normalized');
            },
        });
    }

    function selectedNormalizationScope() {
        return isScreenCompositionValidation ? 'screen' : upload.data.target_scope;
    }

    function normalize(mode) {
        if (!selectedAsset || !baseProfile) return;

        setComparison('normalized');
        setSelectedVariantId(null);
        router.post(`/media/${selectedAsset.id}/normalize`, {
            display_profile_id: baseProfile.id,
            adjustment_mode: mode,
            target_scope: selectedNormalizationScope(),
            ...wallPayload,
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const variantId = Number(page.props.flash?.variant_id);
                if (variantId) setSelectedVariantId(variantId);
                setComparison('normalized');
            },
        });
    }

    function approvePreview() {
        if (!selectedAsset || !baseProfile) return;

        if (selectedVariant) {
            router.post(`/media-variants/${selectedVariant.id}/approve`, {}, { preserveScroll: true });
            return;
        }

        if (!diagnosis?.ok) return;

        setComparison('normalized');
        router.post(`/media/${selectedAsset.id}/normalize`, {
            display_profile_id: baseProfile.id,
            adjustment_mode: 'EDGE_ADJUST',
            target_scope: selectedNormalizationScope(),
            approve: true,
            ...wallPayload,
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const variantId = Number(page.props.flash?.variant_id);
                if (variantId) setSelectedVariantId(variantId);
                setComparison('normalized');
            },
        });
    }

    function publish() {
        if (!deviceId) return;

        const placements = Object.entries(screenAssignments).map(([screen, assetId]) => {
            const asset = assets.find((item) => item.id === assetId);
            const presentation = screenPresentation(asset, wall, comparison, selectedVariantId);

            return {
                screen: Number(screen),
                asset_id: assetId,
                variant_id: presentation?.corrected ? presentation.variant.id : null,
            };
        });

        if (placements.length > 0) {
            router.post('/campaigns/compose-and-publish', {
                device_id: deviceId,
                display_profile_id: baseProfile.id,
                name: campaignName,
                placements,
                ...wallPayload,
            }, { preserveScroll: true });
            return;
        }

        if (!selectedVariant) return;
        router.post(`/media-variants/${selectedVariant.id}/publish`, { device_id: deviceId, name: campaignName, ...wallPayload }, { preserveScroll: true });
    }

    return (
        <>
            <Head title="TV Wall Control" />
            <div className="min-h-screen bg-[#f4f6fa] text-slate-800">
                <header className="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 shadow-[0_4px_20px_rgba(15,23,42,0.04)] backdrop-blur-xl">
                    <div className="mx-auto flex max-w-[1600px] items-center justify-between px-5 py-4 lg:px-8">
                        <div className="flex items-center gap-3">
                            <div className="grid size-10 place-items-center rounded-xl bg-slate-800 text-white shadow-md shadow-slate-300"><Icon name="wall" /></div>
                            <div><p className="text-base font-black tracking-tight text-slate-800">TV WALL <span className="text-indigo-600">CONTROL</span></p><p className="text-[10px] font-semibold uppercase tracking-[.25em] text-slate-400">Operação central</p></div>
                        </div>
                        <div className="flex items-center gap-3 text-xs text-slate-500"><span className="hidden sm:inline">{auth?.user?.name}</span><span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,.10)]" /><button onClick={() => router.post('/logout')} className="rounded-xl border border-slate-200 bg-white px-3 py-2 font-bold text-slate-600 shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">Sair</button></div>
                    </div>
                </header>

                <main className="mx-auto grid max-w-[1600px] gap-6 px-5 py-7 lg:grid-cols-[330px_minmax(0,1fr)] lg:px-8">
                    <aside className="flex flex-col gap-5">
                        <button type="button" className="primary-button inline-flex w-fit items-center justify-center gap-2 px-4 py-2 " onClick={openMediaDialog}><Icon name="upload" className="size-4 shrink-0" /><span>Nova mídia</span></button>

                        <section className="panel-card">
                            <div className="mb-4 flex items-center gap-2"><Icon name="wall" className="size-4 text-indigo-500"/><h2 className="panel-title">Configuração do painel</h2></div>
                            <div className="flex flex-col gap-3">
                                <label className="field-label">Perfil<select className="field" value={upload.data.display_profile_id} onChange={(event) => changeProfile(event.target.value)}>{profiles.map((profile) => <option key={profile.id} value={profile.id}>{profile.name}</option>)}</select></label>

                                <div className="grid grid-cols-2 gap-3">
                                    <label className="field-label">Quantidade de TVs<input className="field" type="number" min="1" max={MAX_TVS} step="1" value={tvCount} onChange={(event) => changeTvCount(event.target.value)} /></label>
                                    <div className="field-label">Orientação
                                        <div className="segmented mt-1">
                                            <button type="button" className={orientation === 'landscape' ? 'active' : ''} onClick={() => setOrientation('landscape')}>Paisagem</button>
                                            <button type="button" className={orientation === 'portrait' ? 'active' : ''} onClick={() => setOrientation('portrait')}>Retrato</button>
                                        </div>
                                    </div>
                                </div>
                                <p className="rounded-xl border border-indigo-100 bg-indigo-50/70 px-3 py-2.5 text-[11px] leading-relaxed text-indigo-700">
                                    Cada TV: {wall.display_width} × {wall.display_height}. Grade automática: {wall.columns} × {wall.rows} ({wall.tv_count} TV{wall.tv_count > 1 ? 's' : ''}). Total: {wall.total_width} × {wall.total_height}.
                                </p>

                                <label className="field-label">Destino da imagem<select className="field" value={upload.data.target_scope} onChange={(event) => upload.setData('target_scope', event.target.value)}><option value="screen">Uma TV ({wall.display_width} × {wall.display_height})</option><option value="wall">Videowall completo ({wall.total_width} × {wall.total_height})</option></select></label>
                            </div>
                        </section>

                        <section className="panel-card">
                            <h2 className="panel-title mb-3">Biblioteca</h2>
                            {assets.length > 0 && <p className="mb-3 text-[11px] leading-relaxed text-slate-500">Clique para selecionar ou arraste uma imagem diretamente para uma TV.</p>}
                            <div className="flex max-h-80 flex-col gap-2 overflow-auto pr-1">
                                {assets.length === 0 && <p className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs leading-relaxed text-slate-500">Nenhuma mídia enviada. O original ficará preservado e a primeira variante será criada após a inspeção.</p>}
                                {assets.map((asset) => (
                                    <div key={asset.id} draggable onDragStart={(event) => event.dataTransfer.setData('text/plain', String(asset.id))} className={`flex items-center gap-2 rounded-xl border p-2 transition ${selectedAsset?.id === asset.id ? 'border-indigo-300 bg-indigo-50/70 shadow-sm' : 'border-slate-200 bg-slate-50/50 hover:border-slate-300 hover:bg-white hover:shadow-sm'}`}>
                                        <button onClick={() => { setSelectedAssetId(asset.id); setSelectedVariantId(null); }} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                                            <img src={asset.original_url} alt="" className="size-12 shrink-0 rounded-lg bg-slate-200 object-cover shadow-sm" />
                                            <span className="min-w-0"><span className="block truncate text-sm font-bold text-slate-800">{asset.name}</span><span className="mt-1 block font-mono text-[10px] text-slate-400">{asset.original_width} × {asset.original_height} · {asset.variants.length} variante(s)</span></span>
                                        </button>
                                        <button type="button" onClick={() => deleteAsset(asset)} className="shrink-0 rounded-lg border border-rose-200 bg-white px-2.5 py-2 text-[10px] font-bold uppercase tracking-wider text-rose-500 transition hover:bg-rose-50 hover:text-rose-700" aria-label={`Apagar ${asset.name}`}>Apagar</button>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </aside>

                    <div className="min-w-0 space-y-6">
                        {flash?.success && <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm"><Icon name="check" className="size-4" />{flash.success}</div>}
                        {flash?.error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm">{flash.error}</div>}
                        <section className="panel-card overflow-hidden p-0">
                            <div className="flex flex-col gap-4 border-b border-slate-200/80 bg-slate-50/40 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                                <div><p className="text-[10px] font-bold uppercase tracking-[.22em] text-indigo-500">Simulador de videowall</p><h1 className="mt-1 text-xl font-black tracking-tight text-slate-800">{selectedAsset?.name ?? 'Prévia da campanha'}</h1></div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="segmented"><button className={comparison === 'original' ? 'active' : ''} onClick={() => setComparison('original')}>Original</button><button className={comparison === 'normalized' ? 'active' : ''} onClick={() => setComparison('normalized')}>Normalizada</button></div>
                                    <button className="icon-button" onClick={() => setZoom(Math.max(.5, zoom - .1))}>−</button><span className="w-12 text-center font-mono text-xs text-slate-500">{Math.round(zoom * 100)}%</span><button className="icon-button" onClick={() => setZoom(Math.min(2, zoom + .1))}>+</button>
                                </div>
                            </div>
                            <VideoWall profile={wall} imageUrl={imageUrl} zoom={zoom} activeScreen={activeScreen} assignments={screenAssignments} assets={assets} comparison={comparison} selectedVariantId={selectedVariantId} compositionMode={compositionMode} onAssign={assignToScreen} onClear={clearScreen} onMove={moveScreenAssignment} onScreenSelect={selectScreen} />
                            <div className="flex flex-wrap items-center gap-2 border-t border-slate-200/80 bg-slate-50/40 px-5 py-4">
                                <button className={`screen-button ${activeScreen === null ? 'active' : ''}`} onClick={() => setActiveScreen(null)}>Visão completa</button>
                                <button className="secondary-button" onClick={openFullscreenPreview} title="Navegue entre as TVs com as setas do teclado">Tela cheia</button>
                                <button className={`secondary-button ${compositionMode === 'screens' && assignedTvCount === wall.tv_count ? 'ring-2 ring-indigo-200' : ''}`} disabled={!selectedAsset} onClick={replicateSelectedImage} title="Repete a imagem completa individualmente em cada TV">Replicar a mesma imagem</button>
                                <button className={`secondary-button ${compositionMode === 'wall' ? 'ring-2 ring-indigo-200' : ''}`} disabled={!selectedAsset} onClick={fillWallWithSelectedImage} title="Usa uma única imagem preenchendo todo o painel">Preencher imagem nas TVs</button>
                                {screens.map((_, index) => <button key={index} className={`screen-button ${activeScreen === index + 1 ? 'active' : ''}`} onClick={() => selectScreen(index + 1)}>TV {index + 1}</button>)}
                                <button className="secondary-button ml-auto" onClick={startNewComposition}>Nova composição</button>
                            </div>
                        </section>

                        <div className="grid gap-6 xl:grid-cols-2">
                            <section className="panel-card">
                                <div className="mb-4 flex items-center justify-between"><h2 className="panel-title">Validação e ajuste</h2>{selectedVariant?.approved_at && <Badge value="succeeded" />}</div>
                                {selectedAsset ? <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="metric"><span>{validationVariant ? 'Corrigida' : 'Recebida'}</span><strong>{receivedResolution}</strong></div>
                                        <div className="metric">
                                            <span>{validationScope === 'screen' ? 'Esperada por TV' : `Esperada no painel (${validationTvCount} TV${validationTvCount === 1 ? '' : 's'})`}</span>
                                            <strong>{isWallValidationPending ? '—' : `${targetWidth} × ${targetHeight}`}</strong>
                                        </div>
                                        <div className="metric"><span>Diagnóstico</span><strong className={diagnosis?.ok ? '!text-emerald-700' : '!text-amber-700'}>{diagnosis?.label}</strong></div>
                                        <div className="metric"><span>Ajuste</span><strong>{validationVariant ? (adjustmentLabels[validationVariant.adjustment_mode] ?? validationVariant.adjustment_mode) : (isScreenCompositionValidation && diagnosis?.ok ? 'Não necessário' : 'Pendente')}</strong></div>
                                    </div>
                                    {diagnosis && !diagnosis.ok && <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] leading-relaxed text-amber-800">{diagnosis.message}</p>}
                                    <div className="flex flex-wrap gap-2">{['EDGE_ADJUST', 'FIT', 'FILL', 'STRETCH'].map((mode) => <button key={mode} className={`secondary-button ${comparison === 'normalized' && selectedVariant?.adjustment_mode === mode ? 'border-indigo-300 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-100' : ''}`} onClick={() => normalize(mode)}>{adjustmentLabels[mode]}</button>)}</div>
                                    <button className="primary-button w-full" disabled={selectedVariant?.approved_at || (!selectedVariant && !diagnosis?.ok)} onClick={approvePreview}>{selectedVariant?.approved_at ? 'Prévia aprovada' : selectedVariant ? 'Confirmar e aprovar prévia' : 'Gerar e aprovar prévia'}</button>
                                </div> : <p className="text-sm text-slate-500">Envie uma mídia para iniciar a validação.</p>}
                            </section>

                            <section className="panel-card">
                                <div className="mb-4 flex items-center gap-2"><Icon name="monitor" className="size-4 text-indigo-500"/><h2 className="panel-title">Publicação</h2></div>
                                <div className="space-y-3">
                                    <label className="field-label">Dispositivo<select className="field" value={deviceId} onChange={(event) => setDeviceId(event.target.value)}>{devices.map((device) => <option key={device.id} value={device.id}>{device.name} · {device.status}</option>)}</select></label>
                                    <label className="field-label">Nome da campanha<input className="field" value={campaignName} onChange={(event) => setCampaignName(event.target.value)} /></label>
                                    <button className="primary-button w-full" disabled={!deviceId || (Object.keys(screenAssignments).length === 0 && !selectedVariant?.approved_at)} onClick={publish}>{Object.keys(screenAssignments).length > 0 ? 'Criar composição e publicar' : 'Publicar no dispositivo'}</button>
                                    {Object.keys(screenAssignments).length > 0 && <p className="rounded-xl border border-indigo-100 bg-indigo-50/70 px-3 py-2.5 text-[11px] text-indigo-700">{Object.keys(screenAssignments).length} TV(s) com imagem própria. As demais permanecerão pretas.</p>}
                                    <p className="text-[11px] leading-relaxed text-slate-400">A publicação só é liberada para uma variante aprovada e compatível com a resolução real informada pelo agente.</p>
                                </div>
                            </section>
                        </div>

                        <section className="panel-card overflow-hidden p-0">
                            <div className="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/40 px-5 py-4"><div className="flex items-center gap-2"><Icon name="activity" className="size-4 text-indigo-500"/><h2 className="panel-title">Operação em tempo real</h2></div><span className="text-[10px] uppercase tracking-widest text-slate-400">últimos 20 comandos</span></div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[720px] text-left text-xs"><thead className="bg-slate-50 text-[10px] uppercase tracking-widest text-slate-400"><tr><th className="px-5 py-3">Dispositivo</th><th className="px-5 py-3">Comando</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Criado</th><th className="px-5 py-3">Resultado</th></tr></thead><tbody className="divide-y divide-slate-100">{commands.map((command) => <tr key={command.id} className="transition hover:bg-slate-50/70"><td className="px-5 py-4 font-semibold text-slate-700">{command.device?.name}</td><td className="px-5 py-4 font-mono text-indigo-600">{command.type}</td><td className="px-5 py-4"><Badge value={command.status} /></td><td className="px-5 py-4 text-slate-500">{new Date(command.created_at).toLocaleString('pt-BR')}</td><td className="max-w-xs truncate px-5 py-4 text-slate-500">{command.error_message ?? (command.finished_at ? 'Confirmado pelo agente' : 'Aguardando')}</td></tr>)}{commands.length === 0 && <tr><td colSpan="5" className="px-5 py-10 text-center text-slate-400">Nenhum comando emitido.</td></tr>}</tbody></table>
                            </div>
                        </section>
                    </div>
                </main>

                {mediaDialogOpen && (
                    <div className="fixed inset-0 z-[90] grid place-items-center overflow-y-auto bg-slate-900/35 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="new-media-title" onMouseDown={(event) => event.target === event.currentTarget && !upload.processing && setMediaDialogOpen(false)}>
                        <section className="panel-card w-full max-w-lg shadow-[0_28px_70px_rgba(15,23,42,0.18)]">
                            <div className="mb-5 flex items-center justify-between gap-4">
                                <div className="flex items-center gap-2"><Icon name="upload" className="size-5 text-indigo-500"/><h2 id="new-media-title" className="text-lg font-black text-slate-800">Nova mídia</h2></div>
                                <button type="button" className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 shadow-sm transition hover:bg-slate-50" disabled={upload.processing} onClick={() => setMediaDialogOpen(false)}>Fechar</button>
                            </div>

                            <form className="flex flex-col gap-4" onSubmit={submitUpload}>
                                <label className="field-label">Nome<input className="field" value={upload.data.name} onChange={(event) => upload.setData('name', event.target.value)} placeholder="Nome da mídia" autoFocus /></label>
                                <label className="group grid cursor-pointer place-items-center rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 px-4 py-9 text-center transition hover:border-indigo-300 hover:bg-indigo-50/50">
                                    <Icon name="upload" className="mb-2 size-7 text-slate-400 transition group-hover:text-indigo-500" />
                                    <span className="max-w-full text-sm font-semibold text-slate-700">{isConverting ? 'Convertendo HEIC/HEIF…' : upload.data.files.length > 0 ? `${upload.data.files.length} imagem(ns) selecionada(s)` : 'Clique para selecionar as imagens'}</span>
                                    <span className="mt-1 text-[10px] text-slate-400">PNG, JPEG, WebP, AVIF, HEIC, GIF ou BMP · até 50 MB</span>
                                    <input type="file" multiple accept="image/png,image/jpeg,image/webp,image/avif,image/heic,image/heif,image/gif,image/bmp,.jpg,.jpeg,.png,.webp,.avif,.heic,.heif,.gif,.bmp" className="sr-only" onChange={selectUploadFiles} />
                                </label>
                                {conversionError && <p className="text-xs text-rose-300">{conversionError}</p>}
                                {Object.values(upload.errors).map((error) => <p key={error} className="text-xs text-rose-300">{error}</p>)}
                                <button className="primary-button" disabled={upload.processing || isConverting || upload.data.files.length === 0 || upload.data.name.trim() === ''}>{upload.processing ? 'Enviando…' : isConverting ? 'Convertendo…' : 'Adicionar à biblioteca'}</button>
                            </form>
                        </section>
                    </div>
                )}

                {fullscreenScreen !== null && (
                    <div className="fixed inset-0 z-[100] grid place-items-center overflow-hidden bg-black text-white" role="dialog" aria-modal="true" aria-label={`Prévia em tela cheia da TV ${fullscreenScreen}`}>
                        <div
                            className={`relative overflow-hidden bg-black shadow-2xl ${fullscreenIssue ? 'ring-4 ring-inset ring-rose-500' : ''}`}
                            style={{
                                width: `min(100vw, calc(100vh * ${(fullscreenIsWall ? wall.total_width : wall.display_width) / (fullscreenIsWall ? wall.total_height : wall.display_height)}))`,
                                height: `min(100vh, calc(100vw * ${(fullscreenIsWall ? wall.total_height : wall.display_height) / (fullscreenIsWall ? wall.total_width : wall.display_width)}))`,
                            }}
                        >
                            {fullscreenAsset ? (
                                fullscreenIsWall
                                    ? <img src={imageUrl} className="absolute inset-0 size-full object-fill" alt={`Imagem exibida no painel completo: ${fullscreenAsset.name}`} />
                                    : <ScreenPreviewImage presentation={fullscreenPresentation} profile={wall} alt={`Imagem exibida na TV ${fullscreenScreen}: ${fullscreenAsset.name}`} />
                            ) : (
                                <div className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_50%_20%,#16253e,#030712_65%)]">
                                    <div className="text-center">
                                        <p className="text-sm font-black uppercase tracking-[.3em] text-slate-500">TV {fullscreenScreen}</p>
                                        <p className="mt-3 text-lg font-semibold text-slate-300">Nenhuma imagem atribuída</p>
                                    </div>
                                </div>
                            )}
                            {fullscreenIssue && <div className="absolute left-1/2 top-24 z-20 max-w-[85%] -translate-x-1/2 rounded-xl border border-rose-300/60 bg-rose-950/95 px-4 py-3 text-center text-sm font-bold text-rose-100 shadow-2xl shadow-black/60 backdrop-blur">{fullscreenIssue}</div>}
                        </div>

                        <div className="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-start justify-between bg-gradient-to-b from-black/85 to-transparent p-5 sm:p-7">
                            <div className="min-w-0 pr-4 drop-shadow-lg">
                                <p className="text-[10px] font-black uppercase tracking-[.25em] text-cyan-300">{fullscreenIsWall ? 'Prévia do painel' : 'Prévia da TV'}</p>
                                <p className="mt-1 text-lg font-black">{fullscreenIsWall ? `${screens.length} TVs · imagem única` : `TV ${fullscreenScreen} de ${screens.length}`}</p>
                                <p className="max-w-[60vw] truncate text-xs text-slate-300">{fullscreenAsset?.name ?? 'Sem imagem'}</p>
                            </div>
                            <button type="button" onClick={closeFullscreenPreview} className="pointer-events-auto rounded-xl border border-white/20 bg-black/50 px-4 py-2 text-xs font-bold backdrop-blur transition hover:bg-white/15">Fechar · Esc</button>
                        </div>

                        {!fullscreenIsWall && <button type="button" onClick={() => moveFullscreen(-1)} className="absolute left-3 top-1/2 z-20 grid size-12 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-black/55 text-2xl shadow-xl backdrop-blur transition hover:scale-105 hover:bg-cyan-400 hover:text-slate-950 sm:left-6 sm:size-14" aria-label="Mostrar imagem anterior da composição">←</button>}
                        {!fullscreenIsWall && <button type="button" onClick={() => moveFullscreen(1)} className="absolute right-3 top-1/2 z-20 grid size-12 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-black/55 text-2xl shadow-xl backdrop-blur transition hover:scale-105 hover:bg-cyan-400 hover:text-slate-950 sm:right-6 sm:size-14" aria-label="Mostrar próxima imagem da composição">→</button>}

                        {!fullscreenIsWall && <div className="absolute bottom-4 left-1/2 z-30 flex max-w-[calc(100vw-7rem)] -translate-x-1/2 items-center gap-2 overflow-x-auto rounded-2xl border border-white/10 bg-black/70 p-2 shadow-2xl backdrop-blur">
                            {compositionScreens.map((screen) => {
                                const asset = assets.find((item) => item.id === screenAssignments[screen]);

                                return (
                                    <button
                                        key={screen}
                                        type="button"
                                        onClick={() => setFullscreenScreen(screen)}
                                        className={`relative size-14 shrink-0 overflow-hidden rounded-lg border-2 transition ${fullscreenScreen === screen ? 'border-cyan-300 ring-2 ring-cyan-300/40' : 'border-white/20 opacity-70 hover:opacity-100'}`}
                                        title={`TV ${screen}: ${asset?.name}`}
                                        aria-label={`Mostrar imagem da TV ${screen}`}
                                    >
                                        <img src={asset?.original_url} className="size-full object-cover" alt="" />
                                        <span className="absolute bottom-0 inset-x-0 bg-black/75 py-0.5 text-[9px] font-black">TV {screen}</span>
                                    </button>
                                );
                            })}
                            <p className="pointer-events-none shrink-0 whitespace-nowrap px-2 text-[11px] font-semibold text-slate-300">Use ← → para trocar de imagem</p>
                        </div>}
                    </div>
                )}
            </div>
        </>
    );
}
