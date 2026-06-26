<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    BrainIcon,
    CheckCircle,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Database,
    ExternalLink,
    Globe,
    Link2,
    LoaderCircle,
    NetworkIcon,
    Play,
    RefreshCw,
    Settings,
    Trash2,
    Unlink,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

import AttachWebsiteDialog from '@/components/ui/AttachWebsiteDialog.vue';
import InfoBanner from '@/components/ui/InfoBanner.vue';
import { useDateFormat } from '@/composables/useDateFormat';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { useEcho } from '@laravel/echo-vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

interface WebSource {
    id: number;
    url: string;
    name?: string;
    status: 'pending' | 'crawling' | 'crawled' | 'ingesting' | 'ingested' | 'failed';
    created_at: string;
    error_message?: string;
    discovered_links?: DiscoveredLink[];
    urls_count?: number;
    agents_count?: number;
    options?: {
        include_paths?: string;
        exclude_paths?: string;
        robots?: boolean;
    };
}
interface DiscoveredLink {
    id: number;
    link: string;
    text: string;
    found_on_url: string;
    crawled_at?: string;
    status?: 'pending' | 'ingesting' | 'ingested' | 'failed';
    source_website_id?: number; // For WebSocket matching
}

const props = defineProps<Props>();

const { t } = useI18n();
const { hasPermission } = useTeamPermissions();

// Check if user can delete sources
const canDeleteSources = computed(() => hasPermission('agent.sources.delete'));

// WebSocket event listener for crawling status updates
useEcho(`agents.${props.agent.external_id}.crawler`, '.WebsiteCrawlStarted', (e) => {
    handleCrawlingUpdate(e);
});

// WebSocket event listener for URL ingestion status updates
useEcho(`agents.${props.agent.external_id}.web.ingestion.url`, '.UrlIngestionStarted', (e) => {
    handleUrlIngestionUpdate(e);
});

// State
const webSources = ref<WebSource[]>([]);
const isLoadingSources = ref(false);
const isInitialLoad = ref(true);

// Usage data for link limits
const usageData = ref<{
    links: {
        limit: number;
        used: number;
        remaining: number;
    };
} | null>(null);

// Form state using Inertia
const websiteForm = useForm({
    url: '',
    robots: true as boolean,
    include_paths: '',
    exclude_paths: '',
});

// Protocol selection state
const selectedProtocol = ref<'https://' | 'http://'>('https://');

// Expanded state for each web source - using Map for better reactivity
const expandedSources = ref<Map<number, boolean>>(new Map());

// Link selection and search state
const selectedLinks = ref<Map<number, Set<number>>>(new Map()); // sourceId -> Set of selected link IDs
const linkSearchQueries = ref<Map<number, string>>(new Map()); // sourceId -> search query

// Search debounce timers
const searchDebounceTimers = ref<Map<number, number>>(new Map());

// Pagination state for each website
const linksPagination = ref<
    Map<
        number,
        {
            currentPage: number;
            hasNextPage: boolean;
            hasPrevPage: boolean;
            totalLinks: number;
            isLoading: boolean;
        }
    >
>(new Map());

// Website selection for training
const selectedWebsites = ref<Set<number>>(new Set()); // Set of selected website IDs
const isTraining = ref(false);

// Settings state
const settingsExpanded = ref(false);

// Crawl settings state
const crawlSettings = ref({
    include_paths: '',
    exclude_paths: '',
});

// Sync crawl settings with form data
const syncFormWithSettings = () => {
    websiteForm.robots = true; // Always respect robots.txt
    websiteForm.include_paths = crawlSettings.value.include_paths;
    websiteForm.exclude_paths = crawlSettings.value.exclude_paths;
};

// Delete state
const deleteDialogOpen = ref(false);
const sourceToDelete = ref<WebSource | null>(null);
const isUnlinking = ref(false);
const isDeletingSource = ref(false);

// Retrain dialog state
const retrainDialogOpen = ref(false);
const websiteToRetrain = ref<WebSource | null>(null);

// Attach website dialog state
const attachWebsiteDialogOpen = ref(false);
const reCrawlNewLinks = ref(false);
const showRetrainButton = ref(false); // Control visibility of retrain website button

// Auto-refresh for crawling sources
const refreshInterval = ref<number | null>(null);

// Links dialog state for viewing all links in a modal
const linksDialogOpen = ref(false);
const currentWebsite = ref<WebSource | null>(null);
const dialogLinks = ref<DiscoveredLink[]>([]);
const dialogLinksLoading = ref(false);
const dialogLinksPagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    hasNextPage: false,
    hasPrevPage: false,
});
const dialogSearchQuery = ref('');
const dialogSearchDebounceTimer = ref<number | null>(null);
const dialogSelectedLinks = ref<Set<number>>(new Set());

// Success animation state for links that just finished training
const linkSuccessStates = ref<Map<number, boolean>>(new Map()); // linkId -> showSuccess
const linkSuccessTimeouts = ref<Map<number, number>>(new Map()); // linkId -> timeoutId

// Separate sources by their processing stage
const crawlingSources = computed(() => {
    return webSources.value.filter((source) => source.status === 'crawling' || source.status === 'pending');
});

const crawledSources = computed(() => {
    return webSources.value.filter((source) => source.status === 'crawled' || source.status === 'failed');
});

const trainingSources = computed(() => {
    return webSources.value.filter((source) => source.status === 'ingesting');
});

const completedSources = computed(() => {
    return webSources.value.filter((source) => source.status === 'ingested');
});

// Computed properties
const _hasCrawlingSources = computed(() => {
    return webSources.value.some((source) => source.status === 'crawling');
});

const isValidUrl = computed(() => {
    const fullUrl = selectedProtocol.value + websiteForm.url.trim();
    if (!fullUrl.trim()) return false;
    try {
        new URL(fullUrl);
        return true;
    } catch {
        return false;
    }
});

const getFullUrl = computed(() => {
    return selectedProtocol.value + websiteForm.url.trim();
});

const updateProtocol = (protocol: 'https://' | 'http://') => {
    selectedProtocol.value = protocol;
};

// Generate crawl criteria message for a source
const getCrawlCriteriaMessage = (source: WebSource): string => {
    if (!source.options) return '';

    const { include_paths, exclude_paths } = source.options;
    const criteria = [];

    if (include_paths?.trim()) {
        criteria.push(t('agents.sources.web.criteria_includes', { paths: include_paths }));
    }

    if (exclude_paths?.trim()) {
        criteria.push(t('agents.sources.web.criteria_excludes', { paths: exclude_paths }));
    }

    return criteria.length > 0 ? t('agents.sources.web.crawl_criteria', { criteria: criteria.join(', ') }) : '';
};

// Website selection computed properties
const selectedWebsiteCount = computed(() => selectedWebsites.value.size);

// Calculate total links for selected websites
const selectedWebsitesTotalLinks = computed(() => {
    let total = 0;
    for (const websiteId of selectedWebsites.value) {
        const source = webSources.value.find((s) => s.id === websiteId);
        total += source?.urls_count || 0;
    }
    return total;
});

// Check if user can train based on selection and remaining quota
const canTrain = computed(() => {
    if (selectedWebsiteCount.value === 0) return false;
    if (!usageData.value) return false;

    const remaining = usageData.value.links.remaining;
    return selectedWebsitesTotalLinks.value <= remaining;
});

// Check if a specific website can be added to selection
const canSelectWebsite = (websiteId: number): boolean => {
    if (!usageData.value) return true; // Allow selection if usage data not loaded yet

    const source = webSources.value.find((s) => s.id === websiteId);
    const websiteLinks = source?.urls_count || 0;

    // If already selected, always allow (for deselection)
    if (selectedWebsites.value.has(websiteId)) return true;

    const remaining = usageData.value.links.remaining;
    const currentTotal = selectedWebsitesTotalLinks.value;

    return currentTotal + websiteLinks <= remaining;
};

const allCrawledWebsitesSelected = computed(() => {
    return crawledSources.value.length > 0 && crawledSources.value.every((source) => selectedWebsites.value.has(source.id));
});
const someCrawledWebsitesSelected = computed(() => {
    return crawledSources.value.some((source) => selectedWebsites.value.has(source.id)) && !allCrawledWebsitesSelected.value;
});

// Dates follow the user's chosen format (Settings → Date & time), with a 24h time.
const { formatDateTime: formatDate } = useDateFormat();

const getDomainFromUrl = (url: string): string => {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
};

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'ingested':
            return 'text-green-600';
        case 'crawling':
        case 'ingesting':
            return 'text-blue-600';
        case 'crawled':
            return 'text-purple-600';
        case 'failed':
            return 'text-red-600';
        case 'pending':
            return 'text-yellow-600';
        default:
            return 'text-gray-600';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'ingested':
            return CheckCircle;
        case 'crawling':
        case 'ingesting':
            return LoaderCircle;
        case 'crawled':
            return NetworkIcon;
        case 'failed':
            return AlertCircle;
        case 'pending':
            return Play;
        default:
            return Play;
    }
};

const getStatusLabel = (status: string): string => {
    switch (status) {
        case 'pending':
            return t('agents.sources.web.status.pending');
        case 'crawling':
            return t('agents.sources.web.status.crawling');
        case 'crawled':
            return t('agents.sources.web.status.crawled');
        case 'ingesting':
            return t('agents.sources.web.status.training');
        case 'ingested':
            return t('agents.sources.web.status.trained');
        case 'failed':
            return t('agents.sources.web.status.failed');
        default:
            return status;
    }
};

// Fetch usage data for link limits
const fetchUsageData = async () => {
    try {
        const response = await axios.get(route('usage'));
        usageData.value = response.data;
    } catch (error) {
        console.error('Error fetching usage data:', error);
    }
};

// Open attach website dialog
const openAttachWebsiteDialog = () => {
    attachWebsiteDialogOpen.value = true;
};

// Handle website attached
const handleWebsiteAttached = () => {
    fetchWebSources();
    fetchUsageData();
};

// Computed properties for link limit warnings
const linkLimitWarning = computed(() => {
    if (!usageData.value) return null;

    const { limit, used, remaining } = usageData.value.links;
    const usagePercentage = (used / limit) * 100;

    if (remaining === 0) {
        return {
            type: 'critical',
            message: t('agents.sources.web.limit_all_used', { limit }),
        };
    } else if (usagePercentage >= 95) {
        return {
            type: 'critical',
            message: t('agents.sources.web.limit_critical', { used, limit, percentage: usagePercentage.toFixed(1), remaining }),
        };
    } else if (usagePercentage >= 80) {
        return {
            type: 'warning',
            message: t('agents.sources.web.limit_warning', { used, limit, percentage: usagePercentage.toFixed(1), remaining }),
        };
    }

    return null;
});

// API functions
const fetchWebsiteLinks = async (websiteId: number, page: number = 1, searchQuery: string = ''): Promise<DiscoveredLink[]> => {
    try {
        // Set loading state
        const currentPagination = linksPagination.value.get(websiteId) || {
            currentPage: 1,
            hasNextPage: false,
            hasPrevPage: false,
            totalLinks: 0,
            isLoading: false,
        };
        linksPagination.value.set(websiteId, { ...currentPagination, isLoading: true });

        // Build params object
        const params: { page: number; q?: string } = { page };
        if (searchQuery.trim()) {
            params.q = searchQuery.trim();
        }

        // Fetch paginated links from API
        const response = await axios.get(
            route('agents.sources.websites.links.index', {
                agent: props.agent.external_id,
                website: websiteId,
            }),
            {
                params,
            },
        );

        // Handle paginated response format
        const paginatedData = response.data;

        if (!paginatedData || !Array.isArray(paginatedData.data)) {
            linksPagination.value.set(websiteId, { ...currentPagination, isLoading: false });
            return [];
        }

        // Convert to DiscoveredLink format
        const discoveredLinks = paginatedData.data.map((linkObj: any) => ({
            id: linkObj.id,
            link: linkObj.url || linkObj.link || linkObj, // Backend uses 'url' field
            text: linkObj.text || linkObj.title || (typeof linkObj === 'string' ? linkObj.split('/').pop() || linkObj : ''),
            found_on_url: linkObj.found_on_url || '',
            crawled_at: linkObj.crawled_at || linkObj.created_at || linkObj.updated_at || '',
            status: linkObj.status || 'ingested',
            source_website_id: linkObj.source_website_id, // Store for WebSocket matching
        }));

        // Update pagination state
        linksPagination.value.set(websiteId, {
            currentPage: paginatedData.current_page || page,
            hasNextPage: !!paginatedData.next_page_url,
            hasPrevPage: !!paginatedData.prev_page_url,
            totalLinks: paginatedData.to || 0,
            isLoading: false,
        });

        return discoveredLinks;
    } catch (error) {
        console.error(`Failed to fetch links for website ${websiteId}:`, error);

        // Reset loading state on error
        const currentPagination = linksPagination.value.get(websiteId);
        if (currentPagination) {
            linksPagination.value.set(websiteId, { ...currentPagination, isLoading: false });
        }

        return [];
    }
};

const fetchWebSources = async (isBackgroundRefresh = false) => {
    if (!isBackgroundRefresh) {
        isLoadingSources.value = true;
    }

    try {
        const response = await axios.get(route('agents.sources.websites.index', { agent: props.agent.external_id }));

        // Transform the response data to match our WebSource interface
        webSources.value = response.data.map((website: any) => {
            // Find existing source to preserve discovered_links
            const existingSource = webSources.value.find((s) => s.id === website.id);

            const source = {
                id: website.id,
                url: website.url,
                name: getDomainFromUrl(website.url),
                status: website.status,
                created_at: website.created_at,
                urls_count: website.urls_count || 0,
                agents_count: website.agents_count || 0,
                options: website.options || {},
                // Preserve discovered_links if they exist
                discovered_links: existingSource?.discovered_links,
            } as WebSource;

            return source;
        });
    } catch (error) {
        console.error('Error fetching web sources:', error);
        toast.error(t('agents.sources.web.load_failed'));
    } finally {
        if (!isBackgroundRefresh) {
            isLoadingSources.value = false;
        }
        isInitialLoad.value = false;
    }
};

const addWebsite = () => {
    try {
        // Client-side validation
        if (!websiteForm.url.trim()) {
            toast.error(t('agents.sources.web.error_enter_url'));
            return;
        }

        if (!isValidUrl.value) {
            toast.error(t('agents.sources.web.error_invalid_url'));
            return;
        }

        // Validate URL protocol
        if (!websiteForm.url.trim()) {
            toast.error(t('agents.sources.web.error_enter_domain'));
            return;
        }

        // Validate URL length
        if (websiteForm.url.length > 2048) {
            toast.error(t('agents.sources.web.error_url_too_long'));
            return;
        }

        // Update form data with current settings
        websiteForm.robots = true; // Always respect robots.txt
        websiteForm.include_paths = crawlSettings.value.include_paths;
        websiteForm.exclude_paths = crawlSettings.value.exclude_paths;

        // Use the main form for submission with the full URL
        websiteForm.url = getFullUrl.value;

        // Submit form with comprehensive error handling
        websiteForm.post(route('agents.sources.websites.store', { agent: props.agent.external_id }), {
            preserveScroll: true,
            onStart: () => {
                console.log('Form submission started');
            },
            onSuccess: (page: any) => {
                // Only add to UI after successful server response
                if (page.props?.website) {
                    const newSource: WebSource = {
                        id: page.props.website.id,
                        url: page.props.website.url,
                        name: getDomainFromUrl(page.props.website.url),
                        status: page.props.website.status || 'pending',
                        created_at: page.props.website.created_at || new Date().toISOString(),
                    };

                    webSources.value.unshift(newSource);
                    toast.success(t('agents.sources.web.added_crawling'));
                } else {
                    // Fallback: refresh the sources list from server
                    fetchWebSources();
                    toast.success(t('agents.sources.web.added'));
                }

                // Reset just the URL field to empty (not the full URL)
                websiteForm.url = '';
            },
            onError: (errors) => {
                console.error('Form errors:', errors);

                // Handle different types of errors
                if (errors.url) {
                    // URL validation error
                    toast.error(t('agents.sources.web.error_url_prefixed', { error: errors.url }));
                } else if (errors.robots) {
                    // Robots setting error
                    toast.error(t('agents.sources.web.error_settings_prefixed', { error: errors.robots }));
                } else if (errors.include_paths) {
                    // Include paths error
                    toast.error(t('agents.sources.web.error_include_paths_prefixed', { error: errors.include_paths }));
                } else if (errors.exclude_paths) {
                    // Exclude paths error
                    toast.error(t('agents.sources.web.error_exclude_paths_prefixed', { error: errors.exclude_paths }));
                } else if (typeof errors === 'string') {
                    // General string error
                    toast.error(errors);
                } else if (errors && typeof errors === 'object') {
                    // Handle other object errors
                    const errorMessages = Object.values(errors).filter((msg) => typeof msg === 'string');
                    if (errorMessages.length > 0) {
                        toast.error(errorMessages[0]);
                    } else {
                        toast.error(t('agents.sources.web.add_failed'));
                    }
                } else {
                    // Fallback error message
                    toast.error(t('agents.sources.web.add_failed_retry'));
                }
            },
            onFinish: () => {
                // Always called after success or error
                console.log('Form submission finished');
            },
        });
    } catch (error) {
        console.error('Unexpected error during form submission:', error);
        toast.error(t('agents.sources.web.unexpected_error'));
    }
};

const confirmDeleteSource = (source: WebSource) => {
    // Check permission first
    if (!canDeleteSources.value) {
        toast.error(t('agents.sources.common.permission_denied'), {
            description: t('agents.sources.common.permission_denied_description'),
        });
        return;
    }

    sourceToDelete.value = source;
    deleteDialogOpen.value = true;
};

const isWebsiteShared = (source: WebSource): boolean => {
    return (source.agents_count ?? 0) > 1;
};

const unlinkWebsite = async () => {
    if (!sourceToDelete.value || isUnlinking.value) return;

    const websiteToUnlink = sourceToDelete.value;
    isUnlinking.value = true;

    try {
        await axios.post(
            route('agents.sources.trained-websites.unlink', {
                agent: props.agent.external_id,
                websiteId: websiteToUnlink.id,
            }),
        );

        toast.success(t('agents.sources.web.unlinked'));

        deleteDialogOpen.value = false;
        sourceToDelete.value = null;

        await fetchWebSources();
        await fetchUsageData();
    } catch (error: any) {
        console.error('Error unlinking website:', error);
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(t('agents.sources.web.unlink_failed', { error: errorMessage }));
    } finally {
        isUnlinking.value = false;
    }
};

const deleteWebSource = async () => {
    if (!sourceToDelete.value || isDeletingSource.value) return;

    isDeletingSource.value = true;

    try {
        await axios.delete(
            route('agents.sources.websites.destroy', {
                agent: props.agent.external_id,
                website: sourceToDelete.value.id,
            }),
        );

        // Remove from local state
        webSources.value = webSources.value.filter((source) => source.id !== sourceToDelete.value!.id);

        // Also remove from selected websites if it was selected
        const newSelected = new Set(selectedWebsites.value);
        newSelected.delete(sourceToDelete.value.id);
        selectedWebsites.value = newSelected;

        // Refresh usage data after deletion
        await fetchUsageData();

        toast.success(t('agents.sources.web.deleted'));
    } catch (error: any) {
        console.error('Error deleting website:', error);
        toast.error(error.response?.data?.message || t('agents.sources.web.delete_failed'));
    } finally {
        isDeletingSource.value = false;
    }

    sourceToDelete.value = null;
    deleteDialogOpen.value = false;
};

// Load links for a specific website and update the source
const loadLinksForWebsite = async (websiteId: number) => {
    try {
        const discoveredLinks = await fetchWebsiteLinks(websiteId);

        // Update the source with discovered links
        const sourceIndex = webSources.value.findIndex((s) => s.id === websiteId);

        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = discoveredLinks;
            // Don't update urls_count here as it's only the first page (50 links)
            // The correct total count will come from fetchWebSources

            // Initialize search query for this source
            if (!linkSearchQueries.value.has(websiteId)) {
                linkSearchQueries.value.set(websiteId, '');
            }
        }

        // Refresh web sources to get the correct urls_count from backend
        await fetchWebSources(true);
    } catch (error) {
        console.error(`Failed to auto-load links for website ${websiteId}:`, error);
        // Don't show error toast for auto-loading, just log it
    }
};

const handleCrawlingUpdate = async (event: any) => {
    // The event structure: { status: "crawling" | "crawled" | "ingesting" | "ingested" | "failed", id: number }
    const { status, id: website_id } = event;

    // Find the source by ID if provided, otherwise find by status
    let sourceIndex = -1;
    if (website_id) {
        sourceIndex = webSources.value.findIndex((source) => source.id === website_id);
    } else {
        // Fallback: find by current status
        sourceIndex = webSources.value.findIndex(
            (source) => source.status === 'crawling' || source.status === 'pending' || source.status === 'crawled' || source.status === 'ingesting',
        );
    }

    // FireCrawl's stage-1 discovery is near-instant, so the "crawled" broadcast
    // can arrive before the just-submitted source has been added to local state
    // by fetchWebSources(). Reconcile from the server before giving up —
    // otherwise the discovered links never load until a manual refresh.
    if (sourceIndex === -1 && website_id) {
        await fetchWebSources(true);
        sourceIndex = webSources.value.findIndex((source) => source.id === website_id);
    }

    if (sourceIndex === -1) {
        return;
    }

    const source = webSources.value[sourceIndex];

    switch (status) {
        case 'crawling':
        case 'started':
            webSources.value[sourceIndex] = {
                ...source,
                status: 'crawling',
            };
            toast.success(t('agents.sources.web.crawling_started', { name: source.name }));
            break;

        case 'crawled':
            webSources.value[sourceIndex] = {
                ...source,
                status: 'crawled',
                urls_count: 0, // We'll get the actual urls_count when we fetch the links
            };
            toast.success(t('agents.sources.web.crawling_completed', { name: source.name }));

            // Always fetch links when crawling finishes, regardless of event data
            if (website_id) {
                loadLinksForWebsite(website_id);
            }

            // Refresh usage data after crawling completes
            await fetchUsageData();
            break;

        case 'ingesting':
            webSources.value[sourceIndex] = {
                ...source,
                status: 'ingesting',
            };
            toast.info(t('agents.sources.web.training_started', { name: source.name }));
            break;

        case 'ingested':
            webSources.value[sourceIndex] = {
                ...source,
                status: 'ingested',
                discovered_links: undefined, // Remove links since they're now trained
            };
            toast.success(t('agents.sources.web.training_completed', { name: source.name }));

            // Clean up selection state for this trained website
            if (website_id) {
                selectedLinks.value.delete(website_id);
                const newSelectedWebsites = new Set(selectedWebsites.value);
                newSelectedWebsites.delete(website_id);
                selectedWebsites.value = newSelectedWebsites;
                linkSearchQueries.value.delete(website_id);
                expandedSources.value.delete(website_id);
                linksPagination.value.delete(website_id);
            }

            // Refresh usage data after training completes
            await fetchUsageData();
            break;

        case 'failed':
            webSources.value[sourceIndex] = {
                ...source,
                status: 'failed',
                error_message: event.error_message || t('agents.sources.web.operation_failed_generic'),
            };
            toast.error(t('agents.sources.web.operation_failed', { name: source.name }));
            break;

        default:
            console.warn('Unknown status received:', status);
            break;
    }
};

const handleUrlIngestionUpdate = (event: any) => {
    // The event structure from backend: { status: string, id: number, url: string, crawled_at: string }
    // Note: source_website_id is NOT included in the event, so we search by link ID
    const status = event.status;
    const link_id = event.id;
    const crawled_at = event.crawled_at;

    if (!link_id) {
        console.warn('Missing link_id in URL ingestion event', event);
        return;
    }

    // Update link status and crawled_at in dialog links if currently viewing them
    if (currentWebsite.value && dialogLinks.value.length > 0) {
        const linkIndex = dialogLinks.value.findIndex((link) => link.id === link_id);

        if (linkIndex !== -1) {
            // Create a new array to trigger reactivity
            const updatedLinks = [...dialogLinks.value];
            updatedLinks[linkIndex] = {
                ...updatedLinks[linkIndex],
                status,
                crawled_at,
            };
            dialogLinks.value = updatedLinks;
        }
    }

    // Search for the link across all website sources and update its status and crawled_at
    const updatedSources = [...webSources.value];
    let linkFound = false;

    for (let sourceIndex = 0; sourceIndex < updatedSources.length; sourceIndex++) {
        const source = updatedSources[sourceIndex];

        if (source.discovered_links) {
            const linkIndex = source.discovered_links.findIndex((link) => link.id === link_id);

            if (linkIndex !== -1) {
                // Found the link - update its status and crawled_at
                const updatedLinks = [...source.discovered_links];
                updatedLinks[linkIndex] = {
                    ...updatedLinks[linkIndex],
                    status,
                    crawled_at,
                };

                updatedSources[sourceIndex] = {
                    ...source,
                    discovered_links: updatedLinks,
                };

                linkFound = true;
                break; // Exit loop once found
            }
        }
    }

    // Only update webSources if we found and modified a link
    if (linkFound) {
        webSources.value = updatedSources;
    }

    // Show toast notifications and handle success animation for status changes
    switch (status) {
        case 'ingesting':
            break;
        case 'ingested':
            // Show success checkmark animation
            linkSuccessStates.value.set(link_id, true);

            // Clear any existing timeout for this link
            const existingTimeout = linkSuccessTimeouts.value.get(link_id);
            if (existingTimeout) {
                clearTimeout(existingTimeout);
            }

            // Hide success animation after 2 seconds
            const timeoutId = window.setTimeout(() => {
                linkSuccessStates.value.delete(link_id);
                linkSuccessTimeouts.value.delete(link_id);
            }, 2000);

            linkSuccessTimeouts.value.set(link_id, timeoutId);
            break;
        case 'failed':
            toast.error(t('agents.sources.web.link_training_failed'));
            break;
    }
};

// Handle collapsible expansion with lazy loading of links
const handleCollapsibleToggle = async (sourceId: number, isExpanding: boolean) => {
    if (!isExpanding) return; // Don't fetch when collapsing

    const source = webSources.value.find((s) => s.id === sourceId);
    if (!source) return;

    // If links are already loaded, don't fetch again
    if (source.discovered_links) return;

    // Only fetch links for crawled or ingested websites
    if (source.status !== 'crawled' && source.status !== 'ingested') return;

    try {
        const discoveredLinks = await fetchWebsiteLinks(sourceId);

        console.log(`Fetched ${discoveredLinks.length} links for website ${sourceId}`);

        // Update the source with discovered links
        const sourceIndex = webSources.value.findIndex((s) => s.id === sourceId);
        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = discoveredLinks;

            // Initialize search query for this source
            if (!linkSearchQueries.value.has(sourceId)) {
                linkSearchQueries.value.set(sourceId, '');
            }
        } else {
            console.warn(`Website ${sourceId} not found in webSources array`);
        }
    } catch (error) {
        console.error(`Failed to fetch links for website ${sourceId}:`, error);
        toast.error(t('agents.sources.web.links_load_failed'));

        // Still update the source to remove loading state by setting empty links
        const sourceIndex = webSources.value.findIndex((s) => s.id === sourceId);
        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = [];
        }
    }
};

// Legacy function for manual collapsible sections (Trained section)
const toggleSourceExpansion = (sourceId: number) => {
    const currentState = expandedSources.value.get(sourceId) || false;
    const newExpanded = new Map(expandedSources.value);
    newExpanded.set(sourceId, !currentState);

    // If expanding and links not loaded, fetch them
    if (!currentState) {
        handleCollapsibleToggle(sourceId, true);

        if (!selectedLinks.value.has(sourceId)) {
            selectedLinks.value.set(sourceId, new Set());
        }
        if (!linkSearchQueries.value.has(sourceId)) {
            linkSearchQueries.value.set(sourceId, '');
        }
    }

    expandedSources.value = newExpanded;
};

// Link selection functions
const toggleLinkSelection = (sourceId: number, linkId: number, checked: boolean | string) => {
    const isChecked = checked === true || checked === 'true';
    const sourceLinks = selectedLinks.value.get(sourceId) || new Set();

    if (isChecked) {
        sourceLinks.add(linkId);
    } else {
        sourceLinks.delete(linkId);
    }

    selectedLinks.value.set(sourceId, sourceLinks);
};

const toggleSelectAllLinks = (sourceId: number, links: DiscoveredLink[], checked: boolean | string | 'indeterminate') => {
    const isChecked = checked === true || checked === 'true';
    const currentSelectedLinks = selectedLinks.value.get(sourceId) || new Set<number>();

    if (isChecked) {
        // Apply search filter to current page links
        const filteredLinks = getFilteredLinks(sourceId, links);

        // Add filtered links to existing selection
        filteredLinks.forEach((link) => currentSelectedLinks.add(link.id));

        selectedLinks.value.set(sourceId, currentSelectedLinks);
    } else {
        // Remove filtered links from selection
        const filteredLinks = getFilteredLinks(sourceId, links);
        filteredLinks.forEach((link) => currentSelectedLinks.delete(link.id));

        selectedLinks.value.set(sourceId, currentSelectedLinks);
    }
};

// Get filtered links - now just returns links as filtering is done server-side
const getFilteredLinks = (sourceId: number, links: DiscoveredLink[]) => {
    // Server-side filtering is now handled by the API
    return links;
};

// Get selection state for checkboxes
const getSelectionState = (sourceId: number, links: DiscoveredLink[]) => {
    const sourceLinks = selectedLinks.value.get(sourceId) || new Set();

    const filteredLinks = getFilteredLinks(sourceId, links);

    if (filteredLinks.length === 0) return { allSelected: false, someSelected: false, selectedCount: 0 };

    const selectedFilteredLinks = filteredLinks.filter((link) => sourceLinks.has(link.id));
    const allSelected = selectedFilteredLinks.length === filteredLinks.length;
    const someSelected = selectedFilteredLinks.length > 0 && selectedFilteredLinks.length < filteredLinks.length;

    return { allSelected, someSelected, selectedCount: sourceLinks.size };
};

// Update search query - triggers API call with debouncing
const updateSearchQuery = (sourceId: number, query: string) => {
    linkSearchQueries.value.set(sourceId, query);

    // Clear existing timer for this source
    const existingTimer = searchDebounceTimers.value.get(sourceId);
    if (existingTimer) {
        clearTimeout(existingTimer);
    }

    // Set new timer for debounced search
    const timerId = window.setTimeout(async () => {
        const searchQuery = linkSearchQueries.value.get(sourceId) || '';
        const discoveredLinks = await fetchWebsiteLinks(sourceId, 1, searchQuery);

        // Update the source with new links
        const sourceIndex = webSources.value.findIndex((s) => s.id === sourceId);
        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = discoveredLinks;
        }

        // Clean up timer
        searchDebounceTimers.value.delete(sourceId);
    }, 500); // 500ms debounce delay

    searchDebounceTimers.value.set(sourceId, timerId);
};

// Pagination navigation functions
const loadNextPage = async (websiteId: number) => {
    const pagination = linksPagination.value.get(websiteId);
    if (!pagination || !pagination.hasNextPage || pagination.isLoading) return;

    const nextPage = pagination.currentPage + 1;
    const searchQuery = linkSearchQueries.value.get(websiteId) || '';
    const discoveredLinks = await fetchWebsiteLinks(websiteId, nextPage, searchQuery);

    // Update the source with new links
    const sourceIndex = webSources.value.findIndex((s) => s.id === websiteId);
    if (sourceIndex !== -1) {
        webSources.value[sourceIndex].discovered_links = discoveredLinks;
    }
};

const loadPrevPage = async (websiteId: number) => {
    const pagination = linksPagination.value.get(websiteId);
    if (!pagination || !pagination.hasPrevPage || pagination.isLoading) return;

    const prevPage = pagination.currentPage - 1;
    const searchQuery = linkSearchQueries.value.get(websiteId) || '';
    const discoveredLinks = await fetchWebsiteLinks(websiteId, prevPage, searchQuery);

    // Update the source with new links
    const sourceIndex = webSources.value.findIndex((s) => s.id === websiteId);
    if (sourceIndex !== -1) {
        webSources.value[sourceIndex].discovered_links = discoveredLinks;
    }
};

// Get total links count for display
const _getTotalLinksCount = (sourceId: number): number => {
    // Get the source's urls_count from backend (most accurate)
    const source = webSources.value.find((s) => s.id === sourceId);
    if (source?.urls_count) {
        return source.urls_count;
    }

    // Fallback to currently displayed links (only for edge cases)
    return source?.discovered_links?.length || 0;
};

// Delete individual link
const deleteLink = async (websiteId: number, linkId: number) => {
    // Check if this is the last link
    const source = webSources.value.find((s) => s.id === websiteId);
    const totalLinks = source?.urls_count || 0;

    if (totalLinks <= 1) {
        toast.error(t('agents.sources.web.cannot_delete_last_link'));
        return;
    }

    try {
        await axios.delete(
            route('agents.sources.websites.links.destroy', {
                agent: props.agent.external_id,
                website: websiteId,
                link: linkId,
            }),
        );

        // Reload the current page of links
        const pagination = linksPagination.value.get(websiteId);
        const currentPage = pagination?.currentPage || 1;
        const searchQuery = linkSearchQueries.value.get(websiteId) || '';
        const discoveredLinks = await fetchWebsiteLinks(websiteId, currentPage, searchQuery);

        // Update the source with new links and decrement urls_count
        const sourceIndex = webSources.value.findIndex((s) => s.id === websiteId);
        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = discoveredLinks;
            // Decrement the urls_count to reflect the deletion
            if (webSources.value[sourceIndex].urls_count && webSources.value[sourceIndex].urls_count! > 0) {
                webSources.value[sourceIndex].urls_count = webSources.value[sourceIndex].urls_count! - 1;
            }
        }

        // Refresh usage data after deletion
        await fetchUsageData();

        toast.success(t('agents.sources.web.link_deleted'));
    } catch (error: any) {
        console.error('Error deleting link:', error);
        toast.error(error.response?.data?.message || t('agents.sources.web.link_delete_failed'));
    }
};

// Delete selected links in bulk
const deleteSelectedLinks = async (websiteId: number) => {
    const selectedLinksForWebsite = selectedLinks.value.get(websiteId);
    if (!selectedLinksForWebsite || selectedLinksForWebsite.size === 0) {
        toast.error(t('agents.sources.web.no_links_selected'));
        return;
    }

    // Check if trying to delete all links
    const source = webSources.value.find((s) => s.id === websiteId);
    const totalLinks = source?.urls_count || 0;

    if (totalLinks > 0 && selectedLinksForWebsite.size >= totalLinks) {
        toast.error(t('agents.sources.web.cannot_delete_all_links'));
        return;
    }

    try {
        await axios.post(
            route('agents.sources.websites.links.bulk-destroy', {
                agent: props.agent.external_id,
                website: websiteId,
            }),
            {
                link_ids: Array.from(selectedLinksForWebsite),
            },
        );

        // Store the count before clearing selection
        const deletedCount = selectedLinksForWebsite.size;

        // Clear selection and reload
        selectedLinks.value.delete(websiteId);

        const pagination = linksPagination.value.get(websiteId);
        const currentPage = pagination?.currentPage || 1;
        const searchQuery = linkSearchQueries.value.get(websiteId) || '';
        const discoveredLinks = await fetchWebsiteLinks(websiteId, currentPage, searchQuery);

        const sourceIndex = webSources.value.findIndex((s) => s.id === websiteId);
        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = discoveredLinks;
            // Decrement the urls_count to reflect the bulk deletion
            if (webSources.value[sourceIndex].urls_count && webSources.value[sourceIndex].urls_count! >= deletedCount) {
                webSources.value[sourceIndex].urls_count = webSources.value[sourceIndex].urls_count! - deletedCount;
            }
        }

        // Refresh usage data after deletion
        await fetchUsageData();

        toast.success(t('agents.sources.web.links_deleted', deletedCount));
    } catch (error: any) {
        console.error('Error deleting links:', error);
        toast.error(error.response?.data?.message || t('agents.sources.web.links_delete_failed'));
    }
};

// Website selection functions
const toggleWebsiteSelection = (websiteId: number, checked: boolean | string) => {
    const isChecked = checked === true || checked === 'true';
    const newSelected = new Set(selectedWebsites.value);

    if (isChecked) {
        // Check if this website can be added without exceeding quota
        if (canSelectWebsite(websiteId)) {
            newSelected.add(websiteId);
            selectedWebsites.value = newSelected;
        } else {
            // Show error message if selection would exceed quota
            const source = webSources.value.find((s) => s.id === websiteId);
            const websiteLinks = source?.urls_count || 0;
            const remaining = usageData.value?.links.remaining || 0;

            if (remaining <= 0) {
                toast.error(t('agents.sources.web.no_links_remaining'), {
                    description: t('agents.sources.web.no_links_remaining_description'),
                    duration: 5000,
                });
            } else {
                const currentTotal = selectedWebsitesTotalLinks.value;
                const exceedBy = currentTotal + websiteLinks - remaining;
                toast.error(t('agents.sources.web.cannot_select'), {
                    description: t('agents.sources.web.cannot_select_description', { links: websiteLinks, exceedBy }),
                    duration: 5000,
                });
            }
            // Don't update selectedWebsites when selection fails
            return;
        }
    } else {
        newSelected.delete(websiteId);
        selectedWebsites.value = newSelected;
    }
};

const toggleSelectAllWebsites = (checked: boolean | string | 'indeterminate') => {
    const isChecked = checked === true || checked === 'true';
    const newSelected = new Set<number>();

    if (isChecked && usageData.value) {
        // Smart select: only select websites that fit within remaining quota
        const remaining = usageData.value.links.remaining;
        let currentTotal = 0;

        for (const source of crawledSources.value) {
            const websiteLinks = source.urls_count || 0;
            if (currentTotal + websiteLinks <= remaining) {
                newSelected.add(source.id);
                currentTotal += websiteLinks;
            }
        }

        // Show message if not all could be selected
        if (newSelected.size < crawledSources.value.length) {
            const skipped = crawledSources.value.length - newSelected.size;
            toast.info(t('agents.sources.web.selected_count', { selected: newSelected.size, total: crawledSources.value.length }), {
                description: t('agents.sources.web.selected_skipped', { skipped, remaining }),
            });
        }
    }

    selectedWebsites.value = newSelected;
};

const trainSelectedWebsites = async () => {
    if (!canTrain.value || isTraining.value) return;

    const selectedIds = Array.from(selectedWebsites.value);
    const _totalLinksToTrain = selectedWebsitesTotalLinks.value;

    isTraining.value = true;
    let successCount = 0;
    const failedWebsites: string[] = [];

    // Process each website individually - train ALL links
    for (const websiteId of selectedIds) {
        try {
            const websiteName =
                webSources.value.find((s) => s.id === websiteId)?.name ||
                getDomainFromUrl(webSources.value.find((s) => s.id === websiteId)?.url || '');

            // Send request to train all links from the website
            await new Promise<void>((resolve, reject) => {
                router.post(
                    route('agents.sources.websites.train', {
                        agent: props.agent.external_id,
                        website: websiteId,
                    }),
                    {
                        // Empty payload means train all links
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            successCount++;
                            resolve();
                        },
                        onError: (errors) => {
                            failedWebsites.push(websiteName);
                            console.error(`Error training website ${websiteId}:`, errors);
                            reject(errors);
                        },
                    },
                );
            });
        } catch (error) {
            console.error(`Failed to train website ${websiteId}:`, error);
            // Continue with next website even if one fails
        }
    }

    // Show results
    if (successCount > 0) {
        selectedWebsites.value = new Set();
        await fetchUsageData();
    }

    if (failedWebsites.length > 0) {
        toast.error(t('agents.sources.web.training_start_failed', { websites: failedWebsites.join(', ') }));
    }

    if (successCount === 0 && failedWebsites.length === 0) {
        toast.error(t('agents.sources.web.training_start_failed_all'));
    }

    isTraining.value = false;
};

// Auto-refresh logic for crawling sources
const _stopRefreshInterval = () => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
        refreshInterval.value = null;
    }
};

// Links dialog functions
const openLinksDialog = async (source: WebSource) => {
    currentWebsite.value = source;
    linksDialogOpen.value = true;
    await fetchDialogLinks(source.id, 1);
};

const closeLinksDialog = () => {
    linksDialogOpen.value = false;
    currentWebsite.value = null;
    dialogLinks.value = [];
    dialogSearchQuery.value = '';
    dialogSelectedLinks.value.clear();
    if (dialogSearchDebounceTimer.value) {
        clearTimeout(dialogSearchDebounceTimer.value);
        dialogSearchDebounceTimer.value = null;
    }
    dialogLinksPagination.value = {
        currentPage: 1,
        lastPage: 1,
        total: 0,
        hasNextPage: false,
        hasPrevPage: false,
    };
};

const fetchDialogLinks = async (websiteId: number, page: number = 1, searchQuery: string = '') => {
    dialogLinksLoading.value = true;
    try {
        const params: { page: number; q?: string } = { page };
        if (searchQuery.trim()) {
            params.q = searchQuery.trim();
        }

        const response = await axios.get(
            route('agents.sources.websites.links.index', {
                agent: props.agent.external_id,
                website: websiteId,
            }),
            {
                params,
            },
        );

        const paginatedData = response.data;

        if (!paginatedData || !Array.isArray(paginatedData.data)) {
            dialogLinks.value = [];
            return;
        }

        dialogLinks.value = paginatedData.data.map((linkObj: any) => ({
            id: linkObj.id,
            link: linkObj.url || linkObj.link || linkObj, // Backend uses 'url' field
            text: linkObj.text || linkObj.title || (typeof linkObj === 'string' ? linkObj.split('/').pop() || linkObj : ''),
            found_on_url: linkObj.found_on_url || '',
            crawled_at: linkObj.crawled_at || linkObj.created_at || linkObj.updated_at || '',
            status: linkObj.status || 'ingested',
            source_website_id: linkObj.source_website_id, // Store for WebSocket matching
        }));

        dialogLinksPagination.value = {
            currentPage: paginatedData.current_page || page,
            lastPage: paginatedData.last_page || 1,
            total: paginatedData.total || 0,
            hasNextPage: !!paginatedData.next_page_url,
            hasPrevPage: !!paginatedData.prev_page_url,
        };
    } catch (error) {
        console.error('Failed to fetch links:', error);
        toast.error(t('agents.sources.web.dialog_links_load_failed'));
    } finally {
        dialogLinksLoading.value = false;
    }
};

const loadNextDialogLinksPage = async () => {
    if (!currentWebsite.value || !dialogLinksPagination.value.hasNextPage || dialogLinksLoading.value) return;
    await fetchDialogLinks(currentWebsite.value.id, dialogLinksPagination.value.currentPage + 1, dialogSearchQuery.value);
};

const loadPrevDialogLinksPage = async () => {
    if (!currentWebsite.value || !dialogLinksPagination.value.hasPrevPage || dialogLinksLoading.value) return;
    await fetchDialogLinks(currentWebsite.value.id, dialogLinksPagination.value.currentPage - 1, dialogSearchQuery.value);
};

const updateDialogSearchQuery = (query: string) => {
    dialogSearchQuery.value = query;

    // Clear existing timer
    if (dialogSearchDebounceTimer.value) {
        clearTimeout(dialogSearchDebounceTimer.value);
    }

    // Set new timer for debounced search
    dialogSearchDebounceTimer.value = window.setTimeout(async () => {
        if (!currentWebsite.value) return;
        await fetchDialogLinks(currentWebsite.value.id, 1, dialogSearchQuery.value);
        dialogSearchDebounceTimer.value = null;
    }, 500); // 500ms debounce delay
};

const deleteDialogLink = async (linkId: number) => {
    if (!currentWebsite.value) return;

    try {
        await axios.delete(
            route('agents.sources.websites.links.destroy', {
                agent: props.agent.external_id,
                website: currentWebsite.value.id,
                link: linkId,
            }),
        );

        toast.success(t('agents.sources.web.link_deleted'));

        // Reload current page with search query
        await fetchDialogLinks(currentWebsite.value.id, dialogLinksPagination.value.currentPage, dialogSearchQuery.value);

        // Refresh usage data
        await fetchUsageData();
    } catch (error: any) {
        console.error('Failed to delete link:', error);
        toast.error(error.response?.data?.message || t('agents.sources.web.link_delete_failed'));
    }
};

// Dialog link selection computed properties
const dialogAllLinksSelected = computed(() => {
    if (dialogLinks.value.length === 0) return false;
    return dialogLinks.value.every((link) => dialogSelectedLinks.value.has(link.id));
});

const dialogSomeLinksSelected = computed(() => {
    if (dialogLinks.value.length === 0) return false;
    const selectedCount = dialogLinks.value.filter((link) => dialogSelectedLinks.value.has(link.id)).length;
    return selectedCount > 0 && selectedCount < dialogLinks.value.length;
});

// Dialog link selection functions
const toggleDialogLinkSelection = (linkId: number, checked: boolean | string) => {
    const isChecked = checked === true || checked === 'true';

    if (isChecked) {
        dialogSelectedLinks.value.add(linkId);
    } else {
        dialogSelectedLinks.value.delete(linkId);
    }
};

const toggleSelectAllDialogLinks = (checked: boolean | string | 'indeterminate') => {
    const isChecked = checked === true || checked === 'true';

    if (isChecked) {
        // Select all links on current page
        dialogLinks.value.forEach((link) => {
            dialogSelectedLinks.value.add(link.id);
        });
    } else {
        // Deselect all links on current page
        dialogLinks.value.forEach((link) => {
            dialogSelectedLinks.value.delete(link.id);
        });
    }
};

const deleteSelectedDialogLinks = async () => {
    if (!currentWebsite.value || dialogSelectedLinks.value.size === 0) {
        toast.error(t('agents.sources.web.no_links_selected'));
        return;
    }

    try {
        await axios.post(
            route('agents.sources.websites.links.bulk-destroy', {
                agent: props.agent.external_id,
                website: currentWebsite.value.id,
            }),
            {
                link_ids: Array.from(dialogSelectedLinks.value),
            },
        );

        const deletedCount = dialogSelectedLinks.value.size;

        // Clear selection
        dialogSelectedLinks.value.clear();

        toast.success(t('agents.sources.web.links_deleted', deletedCount));

        // Reload current page with search query
        await fetchDialogLinks(currentWebsite.value.id, dialogLinksPagination.value.currentPage, dialogSearchQuery.value);

        // Refresh usage data
        await fetchUsageData();
    } catch (error: any) {
        console.error('Failed to delete links:', error);
        toast.error(error.response?.data?.message || t('agents.sources.web.links_delete_failed'));
    }
};

// Retrain individual link
const retrainDialogLink = async (linkId: number) => {
    if (!currentWebsite.value) return;

    try {
        // Optimistically update the status to pending in the UI
        const linkIndex = dialogLinks.value.findIndex((link) => link.id === linkId);
        if (linkIndex !== -1) {
            const updatedLinks = [...dialogLinks.value];
            updatedLinks[linkIndex] = {
                ...updatedLinks[linkIndex],
                status: 'pending',
            };
            dialogLinks.value = updatedLinks;
        }

        // Also update in discovered_links if present
        const sourceIndex = webSources.value.findIndex((source) => source.id === currentWebsite.value!.id);
        if (sourceIndex !== -1 && webSources.value[sourceIndex].discovered_links) {
            const discoveredLinks = webSources.value[sourceIndex].discovered_links;
            if (discoveredLinks) {
                const discLinkIndex = discoveredLinks.findIndex((link) => link.id === linkId);
                if (discLinkIndex !== -1) {
                    const updatedLinks = [...discoveredLinks];
                    updatedLinks[discLinkIndex] = {
                        ...updatedLinks[discLinkIndex],
                        status: 'pending',
                    };

                    const updatedSources = [...webSources.value];
                    updatedSources[sourceIndex] = {
                        ...updatedSources[sourceIndex],
                        discovered_links: updatedLinks,
                    };
                    webSources.value = updatedSources;
                }
            }
        }

        await axios.post(
            route('agents.sources.websites.links.retrain', {
                agent: props.agent.external_id,
                website: currentWebsite.value.id,
                link: linkId,
            }),
        );

        toast.success(t('agents.sources.web.link_retrain_started'));
    } catch (error: any) {
        console.error('Failed to retrain link:', error);
        toast.error(error.response?.data?.message || t('agents.sources.web.link_retrain_failed'));

        // Revert status on error
        const linkIndex = dialogLinks.value.findIndex((link) => link.id === linkId);
        if (linkIndex !== -1) {
            const updatedLinks = [...dialogLinks.value];
            updatedLinks[linkIndex] = {
                ...updatedLinks[linkIndex],
                status: 'ingested',
            };
            dialogLinks.value = updatedLinks;
        }
    }
};

// Open retrain dialog
const openRetrainDialog = (websiteId: number) => {
    const website = webSources.value.find((s) => s.id === websiteId);
    if (website) {
        websiteToRetrain.value = website;
        reCrawlNewLinks.value = false;
        retrainDialogOpen.value = true;
    }
};

// Retrain entire website
const retrainEntireWebsite = async () => {
    if (!websiteToRetrain.value) return;

    try {
        await axios.post(
            route('agents.sources.websites.train', {
                agent: props.agent.external_id,
                website: websiteToRetrain.value.id,
            }),
            {
                recrawl: reCrawlNewLinks.value,
            },
        );

        toast.success(t('agents.sources.web.retrain_started'));

        // Refresh the sources list
        await fetchWebSources();
    } catch (error: any) {
        console.error('Failed to retrain website:', error);
        toast.error(error.response?.data?.message || t('agents.sources.web.retrain_failed'));
    } finally {
        retrainDialogOpen.value = false;
        websiteToRetrain.value = null;
        reCrawlNewLinks.value = false;
    }
};

// Lifecycle
onMounted(() => {
    syncFormWithSettings();
    fetchWebSources();
    fetchUsageData();
});

onUnmounted(() => {
    // Clean up all debounce timers
    searchDebounceTimers.value.forEach((timerId) => {
        clearTimeout(timerId);
    });
    searchDebounceTimers.value.clear();

    // Clean up dialog search debounce timer
    if (dialogSearchDebounceTimer.value) {
        clearTimeout(dialogSearchDebounceTimer.value);
    }

    // Clean up success animation timers
    linkSuccessTimeouts.value.forEach((timerId) => {
        clearTimeout(timerId);
    });
    linkSuccessTimeouts.value.clear();
    linkSuccessStates.value.clear();
});
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <!-- Smart Monitoring Info Banner -->
            <InfoBanner
                :title="t('agents.sources.web.monitoring_title')"
                :message="t('agents.sources.web.monitoring_message')"
                storage-key="smart-monitoring-banner-dismissed"
                variant="info"
            />

            <!-- Link Limit Warning Banner -->
            <Alert v-if="linkLimitWarning" :variant="linkLimitWarning.type === 'critical' ? 'destructive' : 'default'" class="mb-4">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ linkLimitWarning.message }}
                </AlertDescription>
            </Alert>

            <!-- Add Website Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                {{ t('agents.sources.web.crawler_title') }}
                            </CardTitle>
                            <CardDescription> {{ t('agents.sources.web.crawler_description') }} </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" @click="settingsExpanded = !settingsExpanded">
                            <Settings class="me-2 h-4 w-4" />
                            {{ t('agents.sources.web.settings') }}
                            <ChevronDown v-if="!settingsExpanded" class="ms-1 h-4 w-4" />
                            <ChevronUp v-else class="ms-1 h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Link Usage Info -->
                    <div v-if="usageData" class="bg-muted/20 mb-4 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground text-sm">
                                {{ t('agents.sources.web.links_used', { used: usageData.links.used, limit: usageData.links.limit }) }}
                            </span>
                            <span class="text-sm font-medium" :class="usageData.links.remaining <= 0 ? 'text-red-600' : 'text-green-600'">
                                {{ t('agents.sources.web.links_remaining', { remaining: usageData.links.remaining }) }}
                            </span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs">
                            <span v-if="usageData.links.remaining <= 0" class="font-medium text-red-600">
                                {{ t('agents.sources.web.upgrade_required') }}
                            </span>
                            <span v-else-if="usageData.links.used / usageData.links.limit >= 0.9" class="font-medium text-yellow-600">
                                {{ t('agents.sources.web.consider_upgrading') }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex gap-2">
                            <!-- URL Input with Integrated Protocol Dropdown -->
                            <div class="relative flex-1">
                                <div class="flex">
                                    <!-- Protocol Dropdown integrated into input -->
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button
                                                class="bg-muted border-input hover:bg-muted/80 focus:ring-ring flex h-9 items-center rounded-s-md border border-e-0 px-3 py-1 font-mono text-sm focus:ring-1 focus:ring-offset-0 focus:outline-none"
                                            >
                                                {{ selectedProtocol }}
                                                <ChevronDown class="text-muted-foreground ms-1 h-3 w-3" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="start">
                                            <DropdownMenuItem @click="updateProtocol('https://')">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                                    https://
                                                </div>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="updateProtocol('http://')">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-2 w-2 rounded-full bg-orange-500"></div>
                                                    http://
                                                </div>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>

                                    <!-- URL Input -->
                                    <Input
                                        v-model="websiteForm.url"
                                        placeholder="www.example.com"
                                        @keyup.enter="addWebsite"
                                        :disabled="websiteForm.processing"
                                        :class="[
                                            'rounded-s-none border-s-0 focus:border-s-0 focus:ring-offset-0',
                                            { 'border-red-500': websiteForm.errors.url },
                                        ]"
                                        class="flex-1"
                                    />
                                </div>
                                <InputError :message="websiteForm.errors.url" />
                            </div>

                            <!-- Add Button -->
                            <Button @click="addWebsite" :disabled="!isValidUrl || websiteForm.processing">
                                <LoaderCircle v-if="websiteForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                <Globe v-else class="me-2 h-4 w-4" />
                                {{ websiteForm.processing ? t('agents.sources.web.fetching') : t('agents.sources.web.fetch_links') }}
                            </Button>
                        </div>

                        <!-- Collapsible Settings Section -->
                        <Collapsible v-model:open="settingsExpanded">
                            <CollapsibleContent>
                                <div class="bg-muted/20 space-y-4 rounded-lg border p-4">
                                    <div class="mb-3">
                                        <h3 class="text-sm font-semibold">{{ t('agents.sources.web.crawl_settings') }}</h3>
                                    </div>

                                    <!-- Include Paths -->
                                    <div class="space-y-2">
                                        <Label for="include-paths">{{ t('agents.sources.web.include_paths_label') }}</Label>
                                        <Input
                                            id="include-paths"
                                            type="text"
                                            :placeholder="t('agents.sources.web.paths_placeholder')"
                                            v-model="crawlSettings.include_paths"
                                            :class="{ 'border-red-500': websiteForm.errors.include_paths }"
                                            class="text-sm"
                                        />
                                        <InputError :message="websiteForm.errors.include_paths" />
                                        <div class="text-muted-foreground text-xs">
                                            {{ t('agents.sources.web.include_paths_hint') }}
                                        </div>
                                    </div>

                                    <!-- Exclude Paths -->
                                    <div class="space-y-2">
                                        <Label for="exclude-paths">{{ t('agents.sources.web.exclude_paths_label') }}</Label>
                                        <Input
                                            id="exclude-paths"
                                            type="text"
                                            :placeholder="t('agents.sources.web.paths_placeholder')"
                                            class="text-sm"
                                            :class="{ 'border-red-500': websiteForm.errors.exclude_paths }"
                                            v-model="crawlSettings.exclude_paths"
                                        />
                                        <InputError :message="websiteForm.errors.exclude_paths" />
                                        <div class="text-muted-foreground text-xs">
                                            {{ t('agents.sources.web.exclude_paths_hint') }}
                                        </div>
                                    </div>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- General error message for any other form errors -->
                        <div
                            v-if="
                                websiteForm.hasErrors &&
                                !websiteForm.errors.url &&
                                !websiteForm.errors.include_paths &&
                                !websiteForm.errors.exclude_paths
                            "
                            class="space-y-1"
                        >
                            <div v-for="(error, field) in websiteForm.errors" :key="field" class="text-sm text-red-600">
                                {{ Array.isArray(error) ? error[0] : error }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Crawling Websites -->
            <Card v-if="crawlingSources.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <NetworkIcon class="h-5 w-5" />
                        {{ t('agents.sources.web.crawling_title') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('agents.sources.web.crawling_count', crawlingSources.length) }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <template v-for="source in crawlingSources" :key="source.id">
                            <div class="flex items-center justify-between rounded-lg border p-3">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <Globe class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex items-center gap-2">
                                            <span class="truncate font-medium">{{ source.name || getDomainFromUrl(source.url) }}</span>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div class="flex items-center">
                                                            <component
                                                                :is="getStatusIcon(source.status)"
                                                                :class="`h-4 w-4 flex-shrink-0 ${getStatusColor(source.status)} ${source.status === 'crawling' ? 'animate-spin' : ''}`"
                                                            />
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>
                                                            {{
                                                                source.status === 'crawling'
                                                                    ? t('agents.sources.web.tooltip_crawling')
                                                                    : source.status === 'pending'
                                                                      ? t('agents.sources.web.tooltip_ready_crawl')
                                                                      : t('agents.sources.web.tooltip_processing')
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                        <div class="text-muted-foreground text-xs">
                                            <a :href="source.url" target="_blank" class="flex items-center gap-1 hover:underline">
                                                {{ source.url }}
                                                <ExternalLink class="h-3 w-3" />
                                            </a>
                                            <!-- Crawl criteria message -->
                                            <div v-if="getCrawlCriteriaMessage(source)" class="mt-1 text-xs font-medium text-blue-600">
                                                {{ getCrawlCriteriaMessage(source) }}
                                            </div>
                                            <div class="mt-1">
                                                <span> {{ t('agents.sources.web.added_at', { date: formatDate(source.created_at) }) }} </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="confirmDeleteSource(source)"
                                                >
                                                    <span class="sr-only">{{ t('agents.sources.web.remove_source') }}</span>
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ t('agents.sources.web.delete_source') }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <!-- Crawled Websites - Ready for Training -->
            <Card v-if="crawledSources.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <NetworkIcon class="h-5 w-5" />
                        {{ t('agents.sources.web.crawled_title') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('agents.sources.web.crawled_count', crawledSources.length) }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Selection controls -->
                    <div class="bg-muted/30 mb-4 flex items-center justify-between rounded-lg p-3">
                        <div class="flex items-center gap-3">
                            <Checkbox
                                :model-value="allCrawledWebsitesSelected"
                                :indeterminate="someCrawledWebsitesSelected"
                                @update:model-value="toggleSelectAllWebsites"
                                id="select-all-websites"
                                class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                            />
                            <Label for="select-all-websites" class="cursor-pointer text-sm font-medium select-none">
                                {{ t('agents.sources.web.select_all_websites', { count: crawledSources.length }) }}
                            </Label>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-muted-foreground text-sm">
                                <template v-if="selectedWebsiteCount > 0">
                                    {{
                                        t('agents.sources.web.selected_with_links', {
                                            count: selectedWebsiteCount,
                                            links: selectedWebsitesTotalLinks,
                                        })
                                    }}
                                </template>
                                <template v-else> {{ t('agents.sources.web.no_websites_selected') }} </template>
                            </span>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="trainSelectedWebsites" :disabled="!canTrain || isTraining" size="sm">
                                            <LoaderCircle v-if="isTraining" class="me-2 h-4 w-4 animate-spin" />
                                            <Database v-else class="me-2 h-4 w-4" />
                                            {{ t('agents.sources.web.train') }}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent v-if="!canTrain && selectedWebsiteCount > 0 && usageData">
                                        <p>
                                            {{
                                                t('agents.sources.web.train_exceeds_quota', {
                                                    selected: selectedWebsitesTotalLinks,
                                                    remaining: usageData.links.remaining,
                                                })
                                            }}
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <template v-for="source in crawledSources" :key="source.id">
                            <Collapsible
                                v-if="source.urls_count && source.urls_count > 0"
                                v-slot="{ open }"
                                class="rounded-lg border"
                                @update:open="(isOpen) => handleCollapsibleToggle(source.id, isOpen)"
                            >
                                <div class="flex items-center justify-between p-3">
                                    <div class="flex min-w-0 flex-1 items-center gap-3" @click.stop>
                                        <Checkbox
                                            :model-value="selectedWebsites.has(source.id)"
                                            @update:model-value="(checked) => toggleWebsiteSelection(source.id, checked)"
                                            :id="`website-${source.id}`"
                                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary flex-shrink-0"
                                        />
                                        <Globe class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1 flex items-center gap-2">
                                                <span class="truncate font-medium">{{ source.name || getDomainFromUrl(source.url) }}</span>

                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Badge variant="secondary" class="text-xs">
                                                                {{ t('agents.sources.web.links_badge', { count: source.urls_count }) }}
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{{ getStatusLabel(source.status) }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>

                                                <!-- Show warning if website cannot be selected -->
                                                <TooltipProvider v-if="!canSelectWebsite(source.id) && !selectedWebsites.has(source.id) && usageData">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Badge
                                                                variant="outline"
                                                                class="border-yellow-500 text-xs text-yellow-700 dark:text-yellow-400"
                                                            >
                                                                <AlertCircle class="me-1 h-3 w-3" />
                                                                {{
                                                                    usageData.links.remaining <= 0
                                                                        ? t('agents.sources.web.badge_upgrade_required')
                                                                        : t('agents.sources.web.badge_exceeds_quota')
                                                                }}
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p v-if="usageData.links.remaining <= 0">
                                                                {{ t('agents.sources.web.no_links_remaining_plan') }}
                                                            </p>
                                                            <p v-else>
                                                                {{
                                                                    t('agents.sources.web.website_exceeds_remaining', {
                                                                        links: source.urls_count,
                                                                        remaining: usageData.links.remaining,
                                                                    })
                                                                }}
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                <a :href="source.url" target="_blank" class="flex items-center gap-1 hover:underline">
                                                    {{ source.url }}
                                                    <ExternalLink class="h-3 w-3" />
                                                </a>
                                                <!-- Crawl criteria message -->
                                                <div v-if="getCrawlCriteriaMessage(source)" class="mt-1 text-xs font-medium text-blue-600">
                                                    {{ getCrawlCriteriaMessage(source) }}
                                                </div>
                                                <div class="mt-1">
                                                    <span> {{ t('agents.sources.web.added_at', { date: formatDate(source.created_at) }) }} </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <!-- Collapsible Trigger for discovered links -->
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <span class="sr-only">{{
                                                    open ? t('agents.sources.web.collapse_links') : t('agents.sources.web.expand_links')
                                                }}</span>
                                                <ChevronUp v-if="open" class="h-4 w-4" />
                                                <ChevronDown v-else class="h-4 w-4" />
                                            </Button>
                                        </CollapsibleTrigger>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                        size="sm"
                                                        variant="ghost"
                                                        @click="confirmDeleteSource(source)"
                                                    >
                                                        <span class="sr-only">{{ t('agents.sources.web.remove_source') }}</span>
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('agents.sources.web.delete_source') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </div>

                                <!-- Collapsible Content for discovered links -->
                                <CollapsibleContent>
                                    <div class="bg-muted/20 border-t p-3">
                                        <!-- Header with search and controls -->
                                        <div class="mb-3 flex items-center justify-between">
                                            <div class="text-muted-foreground text-sm font-medium">
                                                {{ t('agents.sources.web.discovered_links') }}
                                                <span v-if="linkSearchQueries.get(source.id)?.trim()" class="ms-1 text-xs text-blue-600">{{
                                                    t('agents.sources.web.filtered')
                                                }}</span>
                                            </div>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                @click="deleteSelectedLinks(source.id)"
                                                :disabled="(selectedLinks.get(source.id)?.size || 0) === 0"
                                                class="h-7 text-xs"
                                            >
                                                <Trash2 class="me-1 h-3 w-3" />
                                                {{ t('agents.sources.web.delete_selected_links') }}
                                            </Button>
                                        </div>

                                        <!-- Search box -->
                                        <div v-if="source.discovered_links" class="mb-3">
                                            <Input
                                                :value="linkSearchQueries.get(source.id) || ''"
                                                @input="(e: Event) => updateSearchQuery(source.id, (e.target as HTMLInputElement).value)"
                                                :placeholder="t('agents.sources.web.search_links_placeholder')"
                                                class="h-8 text-xs"
                                            />
                                        </div>

                                        <!-- Select all checkbox -->
                                        <div v-if="source.discovered_links" class="bg-background mb-3 flex items-center gap-2 rounded border p-2">
                                            <Checkbox
                                                :model-value="getSelectionState(source.id, source.discovered_links).allSelected"
                                                :indeterminate="getSelectionState(source.id, source.discovered_links).someSelected"
                                                @update:model-value="
                                                    (checked) => toggleSelectAllLinks(source.id, source.discovered_links || [], checked)
                                                "
                                                :id="`select-all-${source.id}`"
                                                class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                            />
                                            <Label :for="`select-all-${source.id}`" class="cursor-pointer text-xs font-medium select-none">
                                                {{ t('agents.sources.web.select_all_on_page', { count: source.discovered_links.length }) }}
                                            </Label>
                                        </div>

                                        <!-- Links list with infinite scroll -->
                                        <ScrollArea class="h-72 w-full rounded-md border">
                                            <div class="p-4">
                                                <!-- Initial loading state -->
                                                <div
                                                    v-if="!source.discovered_links || linksPagination.get(source.id)?.isLoading"
                                                    class="flex items-center justify-center py-8"
                                                >
                                                    <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
                                                    <span class="text-muted-foreground ms-2 text-sm">{{
                                                        t('agents.sources.web.loading_links')
                                                    }}</span>
                                                </div>

                                                <!-- Links loaded -->
                                                <div v-else class="space-y-2">
                                                    <div
                                                        v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                        :key="link.link"
                                                        class="bg-background hover:bg-muted/50 group flex items-start gap-2 rounded border p-2 text-xs transition-colors"
                                                    >
                                                        <!-- Checkbox -->
                                                        <Checkbox
                                                            :model-value="(selectedLinks.get(source.id) || new Set()).has(link.id)"
                                                            @update:model-value="(checked) => toggleLinkSelection(source.id, link.id, checked)"
                                                            :id="`link-${source.id}-${link.id}`"
                                                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary mt-0.5"
                                                        />
                                                        <ExternalLink class="text-muted-foreground mt-0.5 h-3 w-3 flex-shrink-0" />
                                                        <div class="min-w-0 flex-1">
                                                            <a
                                                                :href="link.link"
                                                                target="_blank"
                                                                class="text-foreground hover:text-primary block truncate text-sm hover:underline"
                                                                @click.stop
                                                            >
                                                                {{ link.link }}
                                                            </a>
                                                        </div>
                                                        <!-- Individual delete button -->
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            @click="deleteLink(source.id, link.id)"
                                                            class="text-destructive hover:text-destructive/80 h-6 w-6 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                        >
                                                            <Trash2 class="h-3 w-3" />
                                                        </Button>
                                                    </div>

                                                    <!-- No results message -->
                                                    <div
                                                        v-if="getFilteredLinks(source.id, source.discovered_links).length === 0"
                                                        class="text-muted-foreground py-4 text-center text-xs"
                                                    >
                                                        {{ t('agents.sources.web.no_links_match') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </ScrollArea>

                                        <!-- Pagination Controls -->
                                        <div v-if="linksPagination.get(source.id)" class="mt-3 flex items-center justify-between px-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="loadPrevPage(source.id)"
                                                :disabled="!linksPagination.get(source.id)?.hasPrevPage || linksPagination.get(source.id)?.isLoading"
                                                class="h-8"
                                            >
                                                <ChevronRight class="me-1 h-4 w-4 rotate-180" />
                                                {{ t('agents.sources.web.previous') }}
                                            </Button>
                                            <span class="text-muted-foreground text-xs">
                                                {{ t('agents.sources.web.page', { page: linksPagination.get(source.id)?.currentPage || 1 }) }}
                                            </span>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="loadNextPage(source.id)"
                                                :disabled="!linksPagination.get(source.id)?.hasNextPage || linksPagination.get(source.id)?.isLoading"
                                                class="h-8"
                                            >
                                                {{ t('agents.sources.web.next') }}
                                                <ChevronRight class="ms-1 h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Sources without links (simplified display) -->
                            <div v-else class="rounded-lg border p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <Checkbox
                                            :model-value="selectedWebsites.has(source.id)"
                                            @update:model-value="(checked) => toggleWebsiteSelection(source.id, checked)"
                                            :id="`website-simple-${source.id}`"
                                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary flex-shrink-0"
                                        />
                                        <Globe class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1 flex items-center gap-2">
                                                <span class="truncate font-medium">{{ source.name || getDomainFromUrl(source.url) }}</span>
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <div class="flex items-center">
                                                                <component
                                                                    :is="getStatusIcon(source.status)"
                                                                    :class="`h-4 w-4 flex-shrink-0 ${getStatusColor(source.status)}`"
                                                                />
                                                            </div>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{{ getStatusLabel(source.status) }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                            <div class="text-muted-foreground text-xs">
                                                <a :href="source.url" target="_blank" class="flex items-center gap-1 hover:underline">
                                                    {{ source.url }}
                                                    <ExternalLink class="h-3 w-3" />
                                                </a>
                                                <!-- Crawl criteria message -->
                                                <div v-if="getCrawlCriteriaMessage(source)" class="mt-1 text-xs font-medium text-blue-600">
                                                    {{ getCrawlCriteriaMessage(source) }}
                                                </div>
                                                <div class="mt-1">
                                                    <span> {{ t('agents.sources.web.added_at', { date: formatDate(source.created_at) }) }} </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                        size="sm"
                                                        variant="ghost"
                                                        @click="confirmDeleteSource(source)"
                                                    >
                                                        <span class="sr-only">{{ t('agents.sources.web.remove_source') }}</span>
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('agents.sources.web.delete_source') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <!-- Training Websites -->
            <Card v-if="trainingSources.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BrainIcon class="h-5 w-5" />
                        {{ t('agents.sources.web.training_title') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('agents.sources.web.training_count', trainingSources.length) }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <template v-for="source in trainingSources" :key="source.id">
                            <div class="flex items-center justify-between rounded-lg border p-3">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <Globe class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex items-center gap-2">
                                            <span class="truncate font-medium">{{ source.name || getDomainFromUrl(source.url) }}</span>
                                        </div>
                                        <div class="text-muted-foreground text-xs">
                                            <a :href="source.url" target="_blank" class="flex items-center gap-1 hover:underline">
                                                {{ source.url }}
                                                <ExternalLink class="h-3 w-3" />
                                            </a>
                                            <div class="mt-1">
                                                <span> {{ t('agents.sources.web.added_at', { date: formatDate(source.created_at) }) }} </span>
                                            </div>

                                            <!-- Loading indicator -->
                                            <div v-if="source.status === 'ingesting'" class="mt-2 flex items-center gap-2 text-blue-600">
                                                <LoaderCircle class="h-3.5 w-3.5 animate-spin" />
                                                <span class="text-xs">{{ t('agents.sources.web.training_in_progress') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <!-- View Links Button -->
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="openLinksDialog(source)"
                                                >
                                                    <Link2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ t('agents.sources.web.view_all_links') }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="confirmDeleteSource(source)"
                                                >
                                                    <span class="sr-only">{{ t('agents.sources.web.remove_source') }}</span>
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ t('agents.sources.web.delete_cancel_training') }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <!-- Trained Knowledge Base -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Database class="h-5 w-5" />
                                {{ t('agents.sources.web.trained_title') }}
                            </CardTitle>
                            <CardDescription>
                                {{ t('agents.sources.web.trained_description', completedSources.length) }}
                            </CardDescription>
                        </div>
                        <Button @click="openAttachWebsiteDialog" variant="outline" size="sm" class="flex items-center gap-2">
                            <Link2 class="h-4 w-4" />
                            {{ t('agents.sources.web.link_website') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-if="isLoadingSources && isInitialLoad" class="text-muted-foreground p-4 text-center">
                            <LoaderCircle class="mx-auto mb-2 h-6 w-6 animate-spin" />
                            {{ t('agents.sources.web.loading_sources') }}
                        </div>
                        <div v-else-if="completedSources.length === 0" class="text-muted-foreground p-4 text-center">
                            {{ t('agents.sources.web.no_websites') }}
                        </div>
                        <template v-else>
                            <ScrollArea v-if="completedSources.length > 5" class="h-[400px] pe-4">
                                <div class="space-y-2">
                                    <template v-for="source in completedSources" :key="source.id">
                                        <div class="rounded-lg border">
                                            <div class="flex items-center justify-between p-3">
                                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                                    <Globe class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                                    <div class="min-w-0 flex-1">
                                                        <div class="mb-1 flex items-center gap-2">
                                                            <span class="truncate font-medium">{{
                                                                source.name || getDomainFromUrl(source.url)
                                                            }}</span>
                                                            <TooltipProvider>
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <div class="flex items-center">
                                                                            <component
                                                                                :is="getStatusIcon(source.status)"
                                                                                :class="`h-4 w-4 flex-shrink-0 ${getStatusColor(source.status)}`"
                                                                            />
                                                                        </div>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        <p>{{ getStatusLabel(source.status) }}</p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            </TooltipProvider>
                                                        </div>
                                                        <div class="text-muted-foreground text-xs">
                                                            <a :href="source.url" target="_blank" class="flex items-center gap-1 hover:underline">
                                                                {{ source.url }}
                                                                <ExternalLink class="h-3 w-3" />
                                                            </a>
                                                            <div class="mt-1">
                                                                <span>
                                                                    {{ t('agents.sources.web.added_at', { date: formatDate(source.created_at) }) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- Error message for failed sources -->
                                                        <div v-if="source.status === 'failed' && source.error_message" class="mt-2">
                                                            <Alert variant="destructive" class="py-2">
                                                                <AlertDescription class="text-xs">
                                                                    {{ source.error_message }}
                                                                </AlertDescription>
                                                            </Alert>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <!-- View Links Button -->
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    @click="openLinksDialog(source)"
                                                                >
                                                                    <Link2 class="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{{ t('agents.sources.web.view_all_links') }}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <!-- Retrain Website Button -->
                                                    <TooltipProvider v-if="showRetrainButton">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    class="h-8 w-8 flex-shrink-0 p-0 text-green-600 hover:bg-green-50 hover:text-green-700"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    @click="openRetrainDialog(source.id)"
                                                                >
                                                                    <RefreshCw class="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{{ t('agents.sources.web.retrain_entire') }}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <!-- Expand/Collapse button for completed sources with links -->
                                                    <Button
                                                        v-if="source.discovered_links && source.discovered_links.length > 0"
                                                        class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                                                        size="sm"
                                                        variant="ghost"
                                                        @click="toggleSourceExpansion(source.id)"
                                                    >
                                                        <span class="sr-only">{{
                                                            expandedSources.get(source.id)
                                                                ? t('agents.sources.web.collapse_links')
                                                                : t('agents.sources.web.expand_links')
                                                        }}</span>
                                                        <ChevronDown v-if="expandedSources.get(source.id)" class="h-4 w-4" />
                                                        <ChevronRight v-else class="h-4 w-4" />
                                                    </Button>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    @click="confirmDeleteSource(source)"
                                                                >
                                                                    <span class="sr-only">{{ t('agents.sources.web.remove_source') }}</span>
                                                                    <Trash2 class="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{{ t('agents.sources.web.delete_source') }}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                            </div>

                                            <!-- Collapsible content for discovered links -->
                                            <div
                                                v-if="source.discovered_links && source.discovered_links.length > 0 && expandedSources.get(source.id)"
                                                class="debug-collapsible"
                                            >
                                                <div class="bg-muted/20 border-t">
                                                    <div class="p-3">
                                                        <!-- Header with search and controls -->
                                                        <div class="mb-3 flex items-center justify-between">
                                                            <div class="text-muted-foreground text-sm font-medium">
                                                                {{ t('agents.sources.web.discovered_links') }}
                                                                <span
                                                                    v-if="linkSearchQueries.get(source.id)?.trim()"
                                                                    class="ms-1 text-xs text-blue-600"
                                                                    >{{ t('agents.sources.web.filtered') }}</span
                                                                >
                                                            </div>
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                @click="deleteSelectedLinks(source.id)"
                                                                :disabled="(selectedLinks.get(source.id)?.size || 0) === 0"
                                                                class="h-7 text-xs"
                                                            >
                                                                <Trash2 class="me-1 h-3 w-3" />
                                                                {{ t('agents.sources.web.delete_selected_links') }}
                                                            </Button>
                                                        </div>

                                                        <!-- Search box -->
                                                        <div class="mb-3">
                                                            <Input
                                                                :value="linkSearchQueries.get(source.id) || ''"
                                                                @input="
                                                                    (e: Event) => updateSearchQuery(source.id, (e.target as HTMLInputElement).value)
                                                                "
                                                                :placeholder="t('agents.sources.web.search_links_placeholder')"
                                                                class="h-8 text-xs"
                                                            />
                                                        </div>

                                                        <!-- Select all checkbox -->
                                                        <div class="bg-background mb-3 flex items-center gap-2 rounded border p-2">
                                                            <Checkbox
                                                                :model-value="getSelectionState(source.id, source.discovered_links).allSelected"
                                                                :indeterminate="getSelectionState(source.id, source.discovered_links).someSelected"
                                                                @update:model-value="
                                                                    (checked) =>
                                                                        toggleSelectAllLinks(source.id, source.discovered_links || [], checked)
                                                                "
                                                                id="`select-all-${source.id}`"
                                                                class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                                            />
                                                            <Label
                                                                :for="`select-all-${source.id}`"
                                                                class="cursor-pointer text-xs font-medium select-none"
                                                            >
                                                                {{
                                                                    t('agents.sources.web.select_all_on_page', {
                                                                        count: source.discovered_links.length,
                                                                    })
                                                                }}
                                                            </Label>
                                                        </div>

                                                        <!-- Links list with infinite scroll -->
                                                        <ScrollArea class="h-72 w-full rounded-md border">
                                                            <div class="p-4">
                                                                <!-- Loading state -->
                                                                <div
                                                                    v-if="linksPagination.get(source.id)?.isLoading"
                                                                    class="flex items-center justify-center py-8"
                                                                >
                                                                    <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
                                                                    <span class="text-muted-foreground ms-2 text-sm">{{
                                                                        t('agents.sources.web.loading_links')
                                                                    }}</span>
                                                                </div>

                                                                <div v-else class="space-y-2">
                                                                    <div
                                                                        v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                                        :key="link.link"
                                                                        class="bg-background hover:bg-muted/50 group flex items-start gap-2 rounded border p-2 text-xs transition-colors"
                                                                    >
                                                                        <Checkbox
                                                                            :model-value="(selectedLinks.get(source.id) || new Set()).has(link.id)"
                                                                            @update:model-value="
                                                                                (checked) => toggleLinkSelection(source.id, link.id, checked)
                                                                            "
                                                                            :id="`link-${source.id}-${link.id}`"
                                                                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary mt-0.5"
                                                                        />
                                                                        <ExternalLink class="text-muted-foreground mt-0.5 h-3 w-3 flex-shrink-0" />
                                                                        <div class="min-w-0 flex-1">
                                                                            <a
                                                                                :href="link.link"
                                                                                target="_blank"
                                                                                class="text-foreground hover:text-primary block truncate text-sm hover:underline"
                                                                                @click.stop
                                                                            >
                                                                                {{ link.link }}
                                                                            </a>
                                                                        </div>
                                                                        <!-- Individual delete button -->
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            @click="deleteLink(source.id, link.id)"
                                                                            class="text-destructive hover:text-destructive/80 h-6 w-6 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                                        >
                                                                            <Trash2 class="h-3 w-3" />
                                                                        </Button>
                                                                    </div>

                                                                    <!-- No results message -->
                                                                    <div
                                                                        v-if="getFilteredLinks(source.id, source.discovered_links || []).length === 0"
                                                                        class="text-muted-foreground py-4 text-center text-xs"
                                                                    >
                                                                        {{ t('agents.sources.web.no_links_match') }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </ScrollArea>

                                                        <!-- Pagination Controls -->
                                                        <div
                                                            v-if="linksPagination.get(source.id)"
                                                            class="mt-3 flex items-center justify-between px-2"
                                                        >
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                @click="loadPrevPage(source.id)"
                                                                :disabled="
                                                                    !linksPagination.get(source.id)?.hasPrevPage ||
                                                                    linksPagination.get(source.id)?.isLoading
                                                                "
                                                                class="h-8"
                                                            >
                                                                <ChevronRight class="me-1 h-4 w-4 rotate-180" />
                                                                {{ t('agents.sources.web.previous') }}
                                                            </Button>
                                                            <span class="text-muted-foreground text-xs">
                                                                {{
                                                                    t('agents.sources.web.page', {
                                                                        page: linksPagination.get(source.id)?.currentPage || 1,
                                                                    })
                                                                }}
                                                            </span>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                @click="loadNextPage(source.id)"
                                                                :disabled="
                                                                    !linksPagination.get(source.id)?.hasNextPage ||
                                                                    linksPagination.get(source.id)?.isLoading
                                                                "
                                                                class="h-8"
                                                            >
                                                                {{ t('agents.sources.web.next') }}
                                                                <ChevronRight class="ms-1 h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </ScrollArea>
                            <div v-else class="space-y-2">
                                <template v-for="source in completedSources" :key="source.id">
                                    <div class="rounded-lg border">
                                        <div class="flex items-center justify-between p-3">
                                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                                <Globe class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="mb-1 flex items-center gap-2">
                                                        <span class="truncate font-medium">{{ source.name || getDomainFromUrl(source.url) }}</span>
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <div class="flex items-center">
                                                                        <component
                                                                            :is="getStatusIcon(source.status)"
                                                                            :class="`h-4 w-4 flex-shrink-0 ${getStatusColor(source.status)}`"
                                                                        />
                                                                    </div>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>{{ getStatusLabel(source.status) }}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </div>
                                                    <div class="text-muted-foreground text-xs">
                                                        <a :href="source.url" target="_blank" class="flex items-center gap-1 hover:underline">
                                                            {{ source.url }}
                                                            <ExternalLink class="h-3 w-3" />
                                                        </a>
                                                        <div class="mt-1">
                                                            <span>
                                                                {{ t('agents.sources.web.added_at', { date: formatDate(source.created_at) }) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <!-- Error message for failed sources -->
                                                    <div v-if="source.status === 'failed' && source.error_message" class="mt-2">
                                                        <Alert variant="destructive" class="py-2">
                                                            <AlertDescription class="text-xs">
                                                                {{ source.error_message }}
                                                            </AlertDescription>
                                                        </Alert>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <!-- View Links Button -->
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                                                                size="sm"
                                                                variant="ghost"
                                                                @click="openLinksDialog(source)"
                                                            >
                                                                <Link2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{{ t('agents.sources.web.view_all_links') }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <!-- Retrain Website Button -->
                                                <TooltipProvider v-if="showRetrainButton">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                class="h-8 w-8 flex-shrink-0 p-0 text-green-600 hover:bg-green-50 hover:text-green-700"
                                                                size="sm"
                                                                variant="ghost"
                                                                @click="openRetrainDialog(source.id)"
                                                            >
                                                                <RefreshCw class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{{ t('agents.sources.web.retrain_entire') }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <!-- Expand/Collapse button for completed sources with links -->
                                                <Button
                                                    v-if="source.discovered_links && source.discovered_links.length > 0"
                                                    class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="toggleSourceExpansion(source.id)"
                                                >
                                                    <span class="sr-only">{{
                                                        expandedSources.get(source.id)
                                                            ? t('agents.sources.web.collapse_links')
                                                            : t('agents.sources.web.expand_links')
                                                    }}</span>
                                                    <ChevronDown v-if="expandedSources.get(source.id)" class="h-4 w-4" />
                                                    <ChevronRight v-else class="h-4 w-4" />
                                                </Button>
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                                                                size="sm"
                                                                variant="ghost"
                                                                @click="confirmDeleteSource(source)"
                                                            >
                                                                <span class="sr-only">{{ t('agents.sources.web.remove_source') }}</span>
                                                                <Trash2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{{ t('agents.sources.web.delete_source') }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                        </div>

                                        <!-- Collapsible content for discovered links -->
                                        <div v-if="source.discovered_links && source.discovered_links.length > 0 && expandedSources.get(source.id)">
                                            <div class="bg-muted/20 border-t">
                                                <div class="p-3">
                                                    <!-- Header with search and controls -->
                                                    <div class="mb-3 flex items-center justify-between">
                                                        <div class="text-muted-foreground text-sm font-medium">
                                                            {{ t('agents.sources.web.discovered_links') }}
                                                            <span
                                                                v-if="linkSearchQueries.get(source.id)?.trim()"
                                                                class="ms-1 text-xs text-blue-600"
                                                                >{{ t('agents.sources.web.filtered') }}</span
                                                            >
                                                        </div>
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            @click="deleteSelectedLinks(source.id)"
                                                            :disabled="(selectedLinks.get(source.id)?.size || 0) === 0"
                                                            class="h-7 text-xs"
                                                        >
                                                            <Trash2 class="me-1 h-3 w-3" />
                                                            {{ t('agents.sources.web.delete_selected_links') }}
                                                        </Button>
                                                    </div>

                                                    <!-- Search box -->
                                                    <div class="mb-3">
                                                        <Input
                                                            :value="linkSearchQueries.get(source.id) || ''"
                                                            @input="(e: Event) => updateSearchQuery(source.id, (e.target as HTMLInputElement).value)"
                                                            :placeholder="t('agents.sources.web.search_links_placeholder')"
                                                            class="h-8 text-xs"
                                                        />
                                                    </div>

                                                    <!-- Select all checkbox -->
                                                    <div class="bg-background mb-3 flex items-center gap-2 rounded border p-2">
                                                        <Checkbox
                                                            :model-value="getSelectionState(source.id, source.discovered_links).allSelected"
                                                            :indeterminate="getSelectionState(source.id, source.discovered_links).someSelected"
                                                            @update:model-value="
                                                                (checked) => toggleSelectAllLinks(source.id, source.discovered_links || [], checked)
                                                            "
                                                            :id="`select-all-${source.id}`"
                                                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                                        />
                                                        <Label
                                                            :for="`select-all-${source.id}`"
                                                            class="cursor-pointer text-xs font-medium select-none"
                                                        >
                                                            {{
                                                                t('agents.sources.web.select_all_on_page', { count: source.discovered_links.length })
                                                            }}
                                                        </Label>
                                                    </div>

                                                    <!-- Links list -->
                                                    <ScrollArea class="h-72 w-full rounded-md border">
                                                        <div class="p-4">
                                                            <!-- Loading state -->
                                                            <div
                                                                v-if="linksPagination.get(source.id)?.isLoading"
                                                                class="flex items-center justify-center py-8"
                                                            >
                                                                <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
                                                                <span class="text-muted-foreground ms-2 text-sm">{{
                                                                    t('agents.sources.web.loading_links')
                                                                }}</span>
                                                            </div>

                                                            <div v-else class="space-y-2">
                                                                <div
                                                                    v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                                    :key="link.link"
                                                                    class="bg-background hover:bg-muted/50 group flex items-start gap-2 rounded border p-2 text-xs transition-colors"
                                                                >
                                                                    <Checkbox
                                                                        :model-value="(selectedLinks.get(source.id) || new Set()).has(link.id)"
                                                                        @update:model-value="
                                                                            (checked) => toggleLinkSelection(source.id, link.id, checked)
                                                                        "
                                                                        :id="`link-${source.id}-${link.id}`"
                                                                        class="data-[state=checked]:bg-primary data-[state=checked]:border-primary mt-0.5"
                                                                    />
                                                                    <ExternalLink class="text-muted-foreground mt-0.5 h-3 w-3 flex-shrink-0" />
                                                                    <div class="min-w-0 flex-1">
                                                                        <a
                                                                            :href="link.link"
                                                                            target="_blank"
                                                                            class="text-foreground hover:text-primary block truncate text-sm hover:underline"
                                                                            @click.stop
                                                                        >
                                                                            {{ link.link }}
                                                                        </a>
                                                                    </div>
                                                                    <!-- Individual delete button -->
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        @click="deleteLink(source.id, link.id)"
                                                                        class="text-destructive hover:text-destructive/80 h-6 w-6 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                                    >
                                                                        <Trash2 class="h-3 w-3" />
                                                                    </Button>
                                                                </div>

                                                                <!-- No results message -->
                                                                <div
                                                                    v-if="getFilteredLinks(source.id, source.discovered_links).length === 0"
                                                                    class="text-muted-foreground py-4 text-center text-xs"
                                                                >
                                                                    {{ t('agents.sources.web.no_links_match') }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </ScrollArea>

                                                    <!-- Pagination Controls -->
                                                    <div v-if="linksPagination.get(source.id)" class="mt-3 flex items-center justify-between px-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            @click="loadPrevPage(source.id)"
                                                            :disabled="
                                                                !linksPagination.get(source.id)?.hasPrevPage ||
                                                                linksPagination.get(source.id)?.isLoading
                                                            "
                                                            class="h-8"
                                                        >
                                                            <ChevronRight class="me-1 h-4 w-4 rotate-180" />
                                                            {{ t('agents.sources.web.previous') }}
                                                        </Button>
                                                        <span class="text-muted-foreground text-xs">
                                                            {{
                                                                t('agents.sources.web.page', {
                                                                    page: linksPagination.get(source.id)?.currentPage || 1,
                                                                })
                                                            }}
                                                        </span>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            @click="loadNextPage(source.id)"
                                                            :disabled="
                                                                !linksPagination.get(source.id)?.hasNextPage ||
                                                                linksPagination.get(source.id)?.isLoading
                                                            "
                                                            class="h-8"
                                                        >
                                                            {{ t('agents.sources.web.next') }}
                                                            <ChevronRight class="ms-1 h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <!-- Delete/Unlink Confirmation Dialog -->
            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent v-if="sourceToDelete" class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ sourceToDelete.name || getDomainFromUrl(sourceToDelete.url) }}</DialogTitle>
                        <DialogDescription>
                            <span v-if="isWebsiteShared(sourceToDelete)">
                                {{ t('agents.sources.web.delete_shared_prefix') }}
                                <strong>{{ t('agents.sources.common.agents_count', { count: sourceToDelete.agents_count }) }}</strong>
                                {{ t('agents.sources.web.delete_shared_suffix') }}
                            </span>
                            <span v-else-if="sourceToDelete.status === 'ingesting' || sourceToDelete.status === 'crawling'">
                                {{ t('agents.sources.web.delete_training_description') }}
                            </span>
                            <span v-else>
                                {{ t('agents.sources.web.delete_description') }}
                            </span>
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <Button variant="outline" @click="deleteDialogOpen = false" :disabled="isUnlinking || isDeletingSource">
                            {{ t('agents.sources.common.cancel') }}
                        </Button>
                        <Button
                            v-if="isWebsiteShared(sourceToDelete)"
                            variant="secondary"
                            @click="unlinkWebsite"
                            :disabled="isUnlinking || isDeletingSource"
                            class="bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50"
                        >
                            <LoaderCircle v-if="isUnlinking" class="me-2 h-4 w-4 animate-spin" />
                            <Unlink v-else class="me-2 h-4 w-4" />
                            {{ isUnlinking ? t('agents.sources.common.unlinking') : t('agents.sources.common.unlink') }}
                        </Button>
                        <Button variant="destructive" @click="deleteWebSource" :disabled="isUnlinking || isDeletingSource">
                            <LoaderCircle v-if="isDeletingSource" class="me-2 h-4 w-4 animate-spin" />
                            <Trash2 v-else class="me-2 h-4 w-4" />
                            <span v-if="isDeletingSource">{{ t('agents.sources.common.deleting') }}</span>
                            <span v-else>{{
                                isWebsiteShared(sourceToDelete) ? t('agents.sources.common.delete_permanently') : t('agents.sources.common.delete')
                            }}</span>
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Retrain Website Dialog -->
            <Dialog v-model:open="retrainDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ t('agents.sources.web.retrain_dialog_title') }}</DialogTitle>
                        <DialogDescription>
                            {{
                                t('agents.sources.web.retrain_dialog_description', {
                                    name: websiteToRetrain?.name || getDomainFromUrl(websiteToRetrain?.url || ''),
                                })
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <div class="bg-muted/50 flex items-start gap-3 rounded-lg border p-3">
                            <Checkbox
                                :id="'recrawl-checkbox'"
                                :checked="reCrawlNewLinks"
                                @update:checked="(checked: boolean) => (reCrawlNewLinks = checked)"
                            />
                            <div class="grid gap-1.5 leading-none">
                                <Label
                                    :for="'recrawl-checkbox'"
                                    class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    {{ t('agents.sources.web.recrawl_label') }}
                                </Label>
                                <p class="text-muted-foreground text-sm">
                                    {{ t('agents.sources.web.recrawl_hint') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="retrainDialogOpen = false"> {{ t('agents.sources.common.cancel') }} </Button>
                        <Button @click="retrainEntireWebsite"> {{ t('agents.sources.web.start_retraining') }} </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Links Dialog Modal -->
            <Dialog v-model:open="linksDialogOpen" @update:open="(open) => !open && closeLinksDialog()">
                <DialogContent class="flex h-[80vh] max-w-4xl flex-col">
                    <DialogHeader>
                        <DialogTitle>{{ t('agents.sources.web.dialog_title') }}</DialogTitle>
                        <DialogDescription>
                            <div class="flex flex-wrap items-center gap-2">
                                <span v-if="currentWebsite">{{
                                    t('agents.sources.web.dialog_manage_links', { name: currentWebsite.name || getDomainFromUrl(currentWebsite.url) })
                                }}</span>
                                <span v-if="dialogLinksPagination.total > 0" class="text-foreground font-medium">
                                    • {{ t('agents.sources.web.dialog_links_total', dialogLinksPagination.total) }}
                                </span>
                            </div>
                        </DialogDescription>
                    </DialogHeader>

                    <!-- Search Box and Controls -->
                    <div class="mb-4 space-y-3">
                        <Input
                            :value="dialogSearchQuery"
                            @input="(e: Event) => updateDialogSearchQuery((e.target as HTMLInputElement).value)"
                            :placeholder="t('agents.sources.web.search_links_placeholder')"
                            class="h-9"
                        />
                        <p v-if="dialogSearchQuery.trim()" class="text-xs text-blue-600">{{ t('agents.sources.web.showing_filtered') }}</p>

                        <!-- Select All and Bulk Delete -->
                        <div v-if="dialogLinks.length > 0" class="bg-muted/30 flex items-center justify-between rounded border p-2">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    :model-value="dialogAllLinksSelected"
                                    :indeterminate="dialogSomeLinksSelected"
                                    @update:model-value="toggleSelectAllDialogLinks"
                                    id="select-all-dialog-links"
                                    class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                />
                                <Label for="select-all-dialog-links" class="cursor-pointer text-xs font-medium select-none">
                                    {{ t('agents.sources.web.select_all_on_page', { count: dialogLinks.length }) }}
                                </Label>
                            </div>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="deleteSelectedDialogLinks"
                                :disabled="dialogSelectedLinks.size === 0"
                                class="h-7 text-xs"
                            >
                                <Trash2 class="me-1 h-3 w-3" />
                                {{ t('agents.sources.web.delete_selected_count', { count: dialogSelectedLinks.size }) }}
                            </Button>
                        </div>
                    </div>

                    <!-- Content Area (scrollable) -->
                    <div class="flex-1 overflow-hidden">
                        <!-- Loading State -->
                        <div v-if="dialogLinksLoading && dialogLinks.length === 0" class="flex items-center justify-center py-12">
                            <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                            <span class="text-muted-foreground ms-2 text-sm">{{ t('agents.sources.web.loading_links') }}</span>
                        </div>

                        <!-- Links List -->
                        <ScrollArea v-else-if="dialogLinks.length > 0" class="h-full pe-4">
                            <div class="space-y-2">
                                <div
                                    v-for="link in dialogLinks"
                                    :key="link.id"
                                    class="bg-background hover:bg-muted/50 flex items-start gap-2 rounded border p-3 transition-colors"
                                >
                                    <Checkbox
                                        :model-value="dialogSelectedLinks.has(link.id)"
                                        @update:model-value="(checked) => toggleDialogLinkSelection(link.id, checked)"
                                        :id="`dialog-link-${link.id}`"
                                        class="data-[state=checked]:bg-primary data-[state=checked]:border-primary mt-0.5 flex-shrink-0"
                                    />
                                    <ExternalLink class="text-muted-foreground mt-0.5 h-3 w-3 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <a
                                            :href="link.link"
                                            target="_blank"
                                            class="text-foreground hover:text-primary block text-sm break-words hover:underline"
                                            @click.stop
                                        >
                                            {{ link.link }}
                                        </a>
                                        <p v-if="link.crawled_at" class="text-muted-foreground mt-1 text-xs">
                                            {{ t('agents.sources.web.crawled_at', { date: formatDate(link.crawled_at) }) }}
                                        </p>
                                    </div>
                                    <div class="flex flex-shrink-0 items-center gap-1">
                                        <!-- Success animation for just-trained links -->
                                        <TooltipProvider v-if="linkSuccessStates.has(link.id)">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div class="flex h-7 w-7 items-center justify-center">
                                                        <CheckCircle2 class="animate-in zoom-in-50 h-4 w-4 text-green-600 duration-300" />
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('agents.sources.web.training_completed_tooltip') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <!-- Loading state for pending or ingesting -->
                                        <TooltipProvider v-else-if="link.status === 'pending' || link.status === 'ingesting'">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div class="flex h-7 w-7 items-center justify-center">
                                                        <LoaderCircle class="h-3.5 w-3.5 animate-spin text-blue-600" />
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        {{
                                                            link.status === 'pending'
                                                                ? t('agents.sources.web.training_pending')
                                                                : t('agents.sources.web.training_in_progress')
                                                        }}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <!-- Failed state -->
                                        <TooltipProvider v-else-if="link.status === 'failed'">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="retrainDialogLink(link.id)"
                                                        class="h-7 w-7 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                                    >
                                                        <AlertCircle class="h-3.5 w-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('agents.sources.web.training_failed_retry') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <!-- Normal retrain button for ingested status -->
                                        <TooltipProvider v-else>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="retrainDialogLink(link.id)"
                                                        class="h-7 w-7 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                    >
                                                        <RefreshCw class="h-3.5 w-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('agents.sources.web.retrain_this_link') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="deleteDialogLink(link.id)"
                                                        class="text-destructive hover:text-destructive/80 h-7 w-7 p-0 hover:bg-red-50"
                                                    >
                                                        <Trash2 class="h-3.5 w-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('agents.sources.web.delete_this_link') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>

                        <!-- Empty State -->
                        <div v-else class="flex flex-col items-center justify-center py-12">
                            <Globe class="text-muted-foreground mb-3 h-12 w-12" />
                            <p class="text-muted-foreground text-sm">
                                {{ dialogSearchQuery.trim() ? t('agents.sources.web.no_links_match') : t('agents.sources.web.no_links_found') }}
                            </p>
                        </div>
                    </div>

                    <!-- Pagination Controls -->
                    <div
                        v-if="dialogLinks.length > 0 || dialogLinksPagination.total > 0"
                        class="mt-4 flex flex-shrink-0 items-center justify-between border-t pt-4"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="loadPrevDialogLinksPage"
                            :disabled="!dialogLinksPagination.hasPrevPage || dialogLinksLoading"
                            class="h-9"
                        >
                            <ChevronRight class="me-1 h-4 w-4 rotate-180" />
                            {{ t('agents.sources.web.previous') }}
                        </Button>
                        <span class="text-muted-foreground text-sm">
                            {{ t('agents.sources.web.page_of', { page: dialogLinksPagination.currentPage, total: dialogLinksPagination.lastPage }) }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="loadNextDialogLinksPage"
                            :disabled="!dialogLinksPagination.hasNextPage || dialogLinksLoading"
                            class="h-9"
                        >
                            {{ t('agents.sources.web.next') }}
                            <ChevronRight class="ms-1 h-4 w-4" />
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </div>

    <!-- Attach Website Dialog -->
    <AttachWebsiteDialog v-model:open="attachWebsiteDialogOpen" :agent-id="agent.external_id" @websiteAttached="handleWebsiteAttached" />
</template>
