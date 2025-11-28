<script setup lang="ts">
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useForm, router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Globe, LoaderCircle, Plus, Trash2, ExternalLink, AlertCircle, CheckCircle, Play, Settings, ChevronDown, ChevronUp, ChevronRight, Database, NetworkIcon, BrainIcon, Link2, RefreshCw, CheckCircle2, Unlink } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import InfoBanner from '@/components/ui/InfoBanner.vue';
import { useEcho } from "@laravel/echo-vue";
import IngestionProgressBar from '@/components/ui/IngestionProgressBar.vue';
import AttachWebsiteDialog from '@/components/ui/AttachWebsiteDialog.vue';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData
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
    progress?: number;
    agents_count?: number;
    options?: {
        include_paths?: string;
        exclude_paths?: string;
        robots?: boolean;
        slow_crawling?: boolean;
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

// WebSocket event listener for crawling status updates
useEcho(
    `agents.${props.agent.id}.crawler`,
    "WebsiteCrawlerEvent",
    (e) => {
        handleCrawlingUpdate(e);
    },
)

// WebSocket event listener for URL ingestion status updates
useEcho(
    `agents.${props.agent.id}.web.ingestion.url`,
    "UrlIngestionEvent",
    (e) => {
        handleUrlIngestionUpdate(e);
    },
)

// WebSocket event listener for website ingestion progress updates
useEcho(
    `agents.${props.agent.id}.crawler.progress`,
    "WebsiteIngestionProgressEvent",
    (e) => {
        handleWebsiteIngestionProgress(e);
    },
)

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
    slow_crawling: false as boolean
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
const linksPagination = ref<Map<number, {
    currentPage: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
    totalLinks: number;
    isLoading: boolean;
}>>(new Map());

// Website selection for training
const selectedWebsites = ref<Set<number>>(new Set()); // Set of selected website IDs
const isTraining = ref(false);

// Settings state
const settingsExpanded = ref(false);

// Crawl settings state
const crawlSettings = ref({
    slow_crawling: false,
    include_paths: '',
    exclude_paths: ''
});

// Sync crawl settings with form data
const syncFormWithSettings = () => {
    websiteForm.robots = true; // Always respect robots.txt
    websiteForm.slow_crawling = crawlSettings.value.slow_crawling;
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
    hasPrevPage: false
});
const dialogSearchQuery = ref('');
const dialogSearchDebounceTimer = ref<number | null>(null);
const dialogSelectedLinks = ref<Set<number>>(new Set());

// Success animation state for links that just finished training
const linkSuccessStates = ref<Map<number, boolean>>(new Map()); // linkId -> showSuccess
const linkSuccessTimeouts = ref<Map<number, number>>(new Map()); // linkId -> timeoutId

// Separate sources by their processing stage
const crawlingSources = computed(() => {
    return webSources.value.filter(source =>
        source.status === 'crawling' || source.status === 'pending'
    );
});

const crawledSources = computed(() => {
    return webSources.value.filter(source =>
        source.status === 'crawled' || source.status === 'failed'
    );
});

const trainingSources = computed(() => {
    return webSources.value.filter(source =>
        source.status === 'ingesting'
    );
});

const completedSources = computed(() => {
    return webSources.value.filter(source =>
        source.status === 'ingested'
    );
});

// Computed properties
const hasCrawlingSources = computed(() => {
    return webSources.value.some(source => source.status === 'crawling');
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
        criteria.push(`includes: ${include_paths}`);
    }

    if (exclude_paths?.trim()) {
        criteria.push(`excludes: ${exclude_paths}`);
    }

    return criteria.length > 0 ? `Crawl criteria: ${criteria.join(', ')}` : '';
};

// Website selection computed properties
const selectedWebsiteCount = computed(() => selectedWebsites.value.size);

// Calculate total links for selected websites
const selectedWebsitesTotalLinks = computed(() => {
    let total = 0;
    for (const websiteId of selectedWebsites.value) {
        const source = webSources.value.find(s => s.id === websiteId);
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

    const source = webSources.value.find(s => s.id === websiteId);
    const websiteLinks = source?.urls_count || 0;

    // If already selected, always allow (for deselection)
    if (selectedWebsites.value.has(websiteId)) return true;

    const remaining = usageData.value.links.remaining;
    const currentTotal = selectedWebsitesTotalLinks.value;

    return (currentTotal + websiteLinks) <= remaining;
};

const allCrawledWebsitesSelected = computed(() => {
    return crawledSources.value.length > 0 && crawledSources.value.every(source => selectedWebsites.value.has(source.id));
});
const someCrawledWebsitesSelected = computed(() => {
    return crawledSources.value.some(source => selectedWebsites.value.has(source.id)) && !allCrawledWebsitesSelected.value;
});



// Utility functions
const formatDate = (dateString: string): string => {
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return dateString;
        }
        return new Intl.DateTimeFormat(navigator.language || 'en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    } catch (error) {
        return dateString;
    }
};

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
            return 'Pending';
        case 'crawling':
            return 'Crawling';
        case 'crawled':
            return 'Crawled';
        case 'ingesting':
            return 'Training';
        case 'ingested':
            return 'Trained';
        case 'failed':
            return 'Failed';
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
            message: `You've used all ${limit} links in your plan. Upgrade to train more links.`
        };
    } else if (usagePercentage >= 95) {
        return {
            type: 'critical',
            message: `You've used ${used}/${limit} links (${usagePercentage.toFixed(1)}%). Only ${remaining} link${remaining !== 1 ? 's' : ''} remaining for training. Consider upgrading your plan.`
        };
    } else if (usagePercentage >= 80) {
        return {
            type: 'warning',
            message: `You've used ${used}/${limit} links (${usagePercentage.toFixed(1)}%). ${remaining} link${remaining !== 1 ? 's' : ''} remaining for training.`
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
            isLoading: false
        };
        linksPagination.value.set(websiteId, { ...currentPagination, isLoading: true });

        // Build params object
        const params: { page: number; q?: string } = { page };
        if (searchQuery.trim()) {
            params.q = searchQuery.trim();
        }

        // Fetch paginated links from API
        const response = await axios.get(route('agents.sources.websites.links', {
            agent: props.agent.id,
            website: websiteId
        }), {
            params
        });

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
            source_website_id: linkObj.source_website_id // Store for WebSocket matching
        }));

        // Update pagination state
        linksPagination.value.set(websiteId, {
            currentPage: paginatedData.current_page || page,
            hasNextPage: !!paginatedData.next_page_url,
            hasPrevPage: !!paginatedData.prev_page_url,
            totalLinks: paginatedData.to || 0,
            isLoading: false
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
        const response = await axios.get(route('agents.sources.websites.list', { agent: props.agent.id }));

        // Transform the response data to match our WebSource interface
        webSources.value = response.data.map((website: any) => {
            // Find existing source to preserve discovered_links
            const existingSource = webSources.value.find(s => s.id === website.id);

            const source = {
                id: website.id,
                url: website.url,
                name: getDomainFromUrl(website.url),
                status: website.status,
                created_at: website.created_at,
                urls_count: website.urls_count || 0,
                progress: website.progress ?? 0,
                agents_count: website.agents_count || 0,
                options: website.options || {},
                // Preserve discovered_links if they exist
                discovered_links: existingSource?.discovered_links
            } as WebSource;

            return source;
        });
    } catch (error) {
        console.error('Error fetching web sources:', error);
        toast.error('Failed to load web sources.');
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
            toast.error('Please enter a website URL.');
            return;
        }

        if (!isValidUrl.value) {
            toast.error('Please enter a valid URL format (e.g., https://example.com).');
            return;
        }

        // Validate URL protocol
        if (!websiteForm.url.trim()) {
            toast.error('Please enter a domain or path');
            return;
        }

        // Validate URL length
        if (websiteForm.url.length > 2048) {
            toast.error('URL is too long. Please use a shorter URL.');
            return;
        }

        // Update form data with current settings
        websiteForm.robots = true; // Always respect robots.txt
        websiteForm.include_paths = crawlSettings.value.include_paths;
        websiteForm.exclude_paths = crawlSettings.value.exclude_paths;
        websiteForm.slow_crawling = crawlSettings.value.slow_crawling;

        // Use the main form for submission with the full URL
        websiteForm.url = getFullUrl.value;

        // Submit form with comprehensive error handling
        websiteForm.post(route('agents.sources.website.crawl', { agent: props.agent.id }), {
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
                        created_at: page.props.website.created_at || new Date().toISOString()
                    };

                webSources.value.unshift(newSource);
                toast.success('Website added and crawling will begin shortly!');
            } else {
                // Fallback: refresh the sources list from server
                fetchWebSources();
                toast.success('Website added successfully!');
            }

            // Reset just the URL field to empty (not the full URL)
            websiteForm.url = '';
        },
        onError: (errors) => {
            console.error('Form errors:', errors);

            // Handle different types of errors
            if (errors.url) {
                // URL validation error
                toast.error(`URL Error: ${errors.url}`);
            } else if (errors.robots) {
                // Robots setting error
                toast.error(`Settings Error: ${errors.robots}`);
            } else if (errors.include_paths) {
                // Include paths error
                toast.error(`Include Paths Error: ${errors.include_paths}`);
            } else if (errors.exclude_paths) {
                // Exclude paths error
                toast.error(`Exclude Paths Error: ${errors.exclude_paths}`);
            } else if (errors.slow_crawling) {
                // Slow crawling error
                toast.error(`Settings Error: ${errors.slow_crawling}`);
            } else if (typeof errors === 'string') {
                // General string error
                toast.error(errors);
            } else if (errors && typeof errors === 'object') {
                // Handle other object errors
                const errorMessages = Object.values(errors).filter(msg => typeof msg === 'string');
                if (errorMessages.length > 0) {
                    toast.error(errorMessages[0]);
                } else {
                    toast.error('Failed to add website. Please check your input.');
                }
            } else {
                // Fallback error message
                toast.error('Failed to add website. Please check your input and try again.');
            }
        },
        onFinish: () => {
            // Always called after success or error
            console.log('Form submission finished');
        }
    });
    } catch (error) {
        console.error('Unexpected error during form submission:', error);
        toast.error('An unexpected error occurred. Please try again.');
    }
};

const confirmDeleteSource = (source: WebSource) => {
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
        await axios.post(route('agents.sources.websites.unlink', {
            agent: props.agent.id,
            websiteId: websiteToUnlink.id
        }));

        toast.success('Website unlinked from agent successfully!');
        
        deleteDialogOpen.value = false;
        sourceToDelete.value = null;

        await fetchWebSources();
        await fetchUsageData();
    } catch (error: any) {
        console.error('Error unlinking website:', error);
        const errorMessage = error.response?.data?.message || error.message;
        toast.error(`Failed to unlink website: ${errorMessage}`);
    } finally {
        isUnlinking.value = false;
    }
};

const deleteWebSource = async () => {
    if (!sourceToDelete.value || isDeletingSource.value) return;

    isDeletingSource.value = true;

    try {
        await axios.delete(route('agents.sources.websites.delete', {
            agent: props.agent.id,
            website: sourceToDelete.value.id
        }));

        // Remove from local state
        webSources.value = webSources.value.filter(source => source.id !== sourceToDelete.value!.id);

        // Also remove from selected websites if it was selected
        const newSelected = new Set(selectedWebsites.value);
        newSelected.delete(sourceToDelete.value.id);
        selectedWebsites.value = newSelected;

        // Refresh usage data after deletion
        await fetchUsageData();

        toast.success('Website deleted successfully.');
    } catch (error: any) {
        console.error('Error deleting website:', error);
        toast.error(error.response?.data?.message || 'Failed to delete website.');
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
        const sourceIndex = webSources.value.findIndex(s => s.id === websiteId);

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
        sourceIndex = webSources.value.findIndex(source => source.id === website_id);
    } else {
        // Fallback: find by current status
        sourceIndex = webSources.value.findIndex(source =>
            source.status === 'crawling' || source.status === 'pending' ||
            source.status === 'crawled' || source.status === 'ingesting'
        );
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
                status: 'crawling'
            };
            toast.success(`Crawling started for ${source.name}`);
            break;

        case 'crawled':
            webSources.value[sourceIndex] = {
                ...source,
                status: 'crawled',
                urls_count: 0 // We'll get the actual urls_count when we fetch the links
            };
            toast.success(`Crawling completed for ${source.name}! Fetching discovered links...`);

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
                status: 'ingesting'
            };
            toast.info(`Training started for ${source.name}`);
            break;

        case 'ingested':
            webSources.value[sourceIndex] = {
                ...source,
                status: 'ingested',
                discovered_links: undefined // Remove links since they're now trained
            };
            toast.success(`Training completed for ${source.name}!`);

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
                error_message: event.error_message || 'Operation failed'
            };
            toast.error(`Operation failed for ${source.name}`);
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
        const linkIndex = dialogLinks.value.findIndex(link => link.id === link_id);

        if (linkIndex !== -1) {
            // Create a new array to trigger reactivity
            const updatedLinks = [...dialogLinks.value];
            updatedLinks[linkIndex] = {
                ...updatedLinks[linkIndex],
                status,
                crawled_at
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
            const linkIndex = source.discovered_links.findIndex(link => link.id === link_id);

            if (linkIndex !== -1) {
                // Found the link - update its status and crawled_at
                const updatedLinks = [...source.discovered_links];
                updatedLinks[linkIndex] = {
                    ...updatedLinks[linkIndex],
                    status,
                    crawled_at
                };

                updatedSources[sourceIndex] = {
                    ...source,
                    discovered_links: updatedLinks
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
            toast.error('Link training failed');
            break;
    }
};

// Handle website ingestion progress updates
const handleWebsiteIngestionProgress = (event: any) => {
    const { website_id, progress } = event;
    
    console.log('WebsiteIngestionProgressEvent received:', { website_id, progress });
    
    if (!website_id || progress === undefined) {
        console.warn('Missing website_id or progress in WebsiteIngestionProgressEvent', event);
        return;
    }

    // Find the website and update its progress
    const sourceIndex = webSources.value.findIndex(source => source.id === website_id);
    
    console.log('Found source at index:', sourceIndex, 'Current sources:', webSources.value.length);
    
    if (sourceIndex !== -1) {
        // Create a new array with the updated source for reactivity
        const updatedSources = [...webSources.value];
        updatedSources[sourceIndex] = {
            ...updatedSources[sourceIndex],
            progress
        };
        webSources.value = updatedSources;
        console.log('Updated source progress to:', progress);
    }
};


// Handle collapsible expansion with lazy loading of links
const handleCollapsibleToggle = async (sourceId: number, isExpanding: boolean) => {
    if (!isExpanding) return; // Don't fetch when collapsing

    const source = webSources.value.find(s => s.id === sourceId);
    if (!source) return;

    // If links are already loaded, don't fetch again
    if (source.discovered_links) return;

    // Only fetch links for crawled or ingested websites
    if (source.status !== 'crawled' && source.status !== 'ingested') return;

    try {
        const discoveredLinks = await fetchWebsiteLinks(sourceId);

        console.log(`Fetched ${discoveredLinks.length} links for website ${sourceId}`);

        // Update the source with discovered links
        const sourceIndex = webSources.value.findIndex(s => s.id === sourceId);
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
        toast.error(`Failed to load links for website. Please try again.`);

        // Still update the source to remove loading state by setting empty links
        const sourceIndex = webSources.value.findIndex(s => s.id === sourceId);
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
        filteredLinks.forEach(link => currentSelectedLinks.add(link.id));

        selectedLinks.value.set(sourceId, currentSelectedLinks);
    } else {
        // Remove filtered links from selection
        const filteredLinks = getFilteredLinks(sourceId, links);
        filteredLinks.forEach(link => currentSelectedLinks.delete(link.id));

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

    const selectedFilteredLinks = filteredLinks.filter(link => sourceLinks.has(link.id));
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
        const sourceIndex = webSources.value.findIndex(s => s.id === sourceId);
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
    const sourceIndex = webSources.value.findIndex(s => s.id === websiteId);
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
    const sourceIndex = webSources.value.findIndex(s => s.id === websiteId);
    if (sourceIndex !== -1) {
        webSources.value[sourceIndex].discovered_links = discoveredLinks;
    }
};





// Get total links count for display
const getTotalLinksCount = (sourceId: number): number => {
    // Get the source's urls_count from backend (most accurate)
    const source = webSources.value.find(s => s.id === sourceId);
    if (source?.urls_count) {
        return source.urls_count;
    }

    // Fallback to currently displayed links (only for edge cases)
    return source?.discovered_links?.length || 0;
};

// Delete individual link
const deleteLink = async (websiteId: number, linkId: number) => {
    // Check if this is the last link
    const source = webSources.value.find(s => s.id === websiteId);
    const totalLinks = source?.urls_count || 0;

    if (totalLinks <= 1) {
        toast.error('Cannot delete the last link. At least one link must remain.');
        return;
    }

    try {
        await axios.delete(route('agents.sources.websites.links.delete', {
            agent: props.agent.id,
            website: websiteId,
            link: linkId
        }));

        // Reload the current page of links
        const pagination = linksPagination.value.get(websiteId);
        const currentPage = pagination?.currentPage || 1;
        const searchQuery = linkSearchQueries.value.get(websiteId) || '';
        const discoveredLinks = await fetchWebsiteLinks(websiteId, currentPage, searchQuery);

        // Update the source with new links and decrement urls_count
        const sourceIndex = webSources.value.findIndex(s => s.id === websiteId);
        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = discoveredLinks;
            // Decrement the urls_count to reflect the deletion
            if (webSources.value[sourceIndex].urls_count && webSources.value[sourceIndex].urls_count! > 0) {
                webSources.value[sourceIndex].urls_count = webSources.value[sourceIndex].urls_count! - 1;
            }
        }

        // Refresh usage data after deletion
        await fetchUsageData();

        toast.success('Link deleted successfully.');
    } catch (error: any) {
        console.error('Error deleting link:', error);
        toast.error(error.response?.data?.message || 'Failed to delete link.');
    }
};

// Delete selected links in bulk
const deleteSelectedLinks = async (websiteId: number) => {
    const selectedLinksForWebsite = selectedLinks.value.get(websiteId);
    if (!selectedLinksForWebsite || selectedLinksForWebsite.size === 0) {
        toast.error('No links selected for deletion.');
        return;
    }

    // Check if trying to delete all links
    const source = webSources.value.find(s => s.id === websiteId);
    const totalLinks = source?.urls_count || 0;

    if (totalLinks > 0 && selectedLinksForWebsite.size >= totalLinks) {
        toast.error('Cannot delete all links. At least one link must remain.');
        return;
    }

    try {
        await axios.post(route('agents.sources.websites.links.bulk-delete', {
            agent: props.agent.id,
            website: websiteId
        }), {
            link_ids: Array.from(selectedLinksForWebsite)
        });

        // Store the count before clearing selection
        const deletedCount = selectedLinksForWebsite.size;

        // Clear selection and reload
        selectedLinks.value.delete(websiteId);

        const pagination = linksPagination.value.get(websiteId);
        const currentPage = pagination?.currentPage || 1;
        const searchQuery = linkSearchQueries.value.get(websiteId) || '';
        const discoveredLinks = await fetchWebsiteLinks(websiteId, currentPage, searchQuery);

        const sourceIndex = webSources.value.findIndex(s => s.id === websiteId);
        if (sourceIndex !== -1) {
            webSources.value[sourceIndex].discovered_links = discoveredLinks;
            // Decrement the urls_count to reflect the bulk deletion
            if (webSources.value[sourceIndex].urls_count && webSources.value[sourceIndex].urls_count! >= deletedCount) {
                webSources.value[sourceIndex].urls_count = webSources.value[sourceIndex].urls_count! - deletedCount;
            }
        }

        // Refresh usage data after deletion
        await fetchUsageData();

        toast.success(`Deleted ${deletedCount} link${deletedCount !== 1 ? 's' : ''} successfully.`);
    } catch (error: any) {
        console.error('Error deleting links:', error);
        toast.error(error.response?.data?.message || 'Failed to delete links.');
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
            const source = webSources.value.find(s => s.id === websiteId);
            const websiteLinks = source?.urls_count || 0;
            const remaining = usageData.value?.links.remaining || 0;

            if (remaining <= 0) {
                toast.error('No links remaining', {
                    description: 'Please upgrade your plan to train websites.',
                    duration: 5000
                });
            } else {
                const currentTotal = selectedWebsitesTotalLinks.value;
                const exceedBy = (currentTotal + websiteLinks) - remaining;
                toast.error('Cannot select website', {
                    description: `${websiteLinks} links would exceed your quota by ${exceedBy}. Deselect other websites or upgrade.`,
                    duration: 5000
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
            toast.info(`Selected ${newSelected.size} of ${crawledSources.value.length} websites`, {
                description: `${skipped} website${skipped !== 1 ? 's were' : ' was'} skipped to stay within your ${remaining} link limit.`
            });
        }
    }

    selectedWebsites.value = newSelected;
};

const trainSelectedWebsites = async () => {
    if (!canTrain.value || isTraining.value) return;

    const selectedIds = Array.from(selectedWebsites.value);
    const totalLinksToTrain = selectedWebsitesTotalLinks.value;

    isTraining.value = true;
    let successCount = 0;
    let failedWebsites: string[] = [];

    // Process each website individually - train ALL links
    for (const websiteId of selectedIds) {
        try {
            const websiteName = webSources.value.find(s => s.id === websiteId)?.name ||
                              getDomainFromUrl(webSources.value.find(s => s.id === websiteId)?.url || '');

            // Send request to train all links from the website
            await new Promise<void>((resolve, reject) => {
                router.post(route('agents.sources.website.train', {
                    agent: props.agent.id,
                    website: websiteId
                }), {
                    // Empty payload means train all links
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        successCount++;
                        resolve();
                    },
                    onError: (errors) => {
                        failedWebsites.push(websiteName);
                        console.error(`Error training website ${websiteId}:`, errors);
                        reject(errors);
                    }
                });
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
        toast.error(`Failed to start training for: ${failedWebsites.join(', ')}`);
    }

    if (successCount === 0 && failedWebsites.length === 0) {
        toast.error('Failed to start training for any websites.');
    }

    isTraining.value = false;
};

// Auto-refresh logic for crawling sources
const stopRefreshInterval = () => {
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
        hasPrevPage: false
    };
};

const fetchDialogLinks = async (websiteId: number, page: number = 1, searchQuery: string = '') => {
    dialogLinksLoading.value = true;
    try {
        const params: { page: number; q?: string } = { page };
        if (searchQuery.trim()) {
            params.q = searchQuery.trim();
        }

        const response = await axios.get(route('agents.sources.websites.links', {
            agent: props.agent.id,
            website: websiteId
        }), {
            params
        });

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
            source_website_id: linkObj.source_website_id // Store for WebSocket matching
        }));

        dialogLinksPagination.value = {
            currentPage: paginatedData.current_page || page,
            lastPage: paginatedData.last_page || 1,
            total: paginatedData.total || 0,
            hasNextPage: !!paginatedData.next_page_url,
            hasPrevPage: !!paginatedData.prev_page_url
        };
    } catch (error) {
        console.error('Failed to fetch links:', error);
        toast.error('Failed to load links');
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
        await axios.delete(route('agents.sources.websites.links.delete', {
            agent: props.agent.id,
            website: currentWebsite.value.id,
            link: linkId
        }));

        toast.success('Link deleted successfully');

        // Reload current page with search query
        await fetchDialogLinks(currentWebsite.value.id, dialogLinksPagination.value.currentPage, dialogSearchQuery.value);

        // Refresh usage data
        await fetchUsageData();
    } catch (error: any) {
        console.error('Failed to delete link:', error);
        toast.error(error.response?.data?.message || 'Failed to delete link');
    }
};

// Dialog link selection computed properties
const dialogAllLinksSelected = computed(() => {
    if (dialogLinks.value.length === 0) return false;
    return dialogLinks.value.every(link => dialogSelectedLinks.value.has(link.id));
});

const dialogSomeLinksSelected = computed(() => {
    if (dialogLinks.value.length === 0) return false;
    const selectedCount = dialogLinks.value.filter(link => dialogSelectedLinks.value.has(link.id)).length;
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
        dialogLinks.value.forEach(link => {
            dialogSelectedLinks.value.add(link.id);
        });
    } else {
        // Deselect all links on current page
        dialogLinks.value.forEach(link => {
            dialogSelectedLinks.value.delete(link.id);
        });
    }
};

const deleteSelectedDialogLinks = async () => {
    if (!currentWebsite.value || dialogSelectedLinks.value.size === 0) {
        toast.error('No links selected for deletion.');
        return;
    }

    try {
        await axios.post(route('agents.sources.websites.links.bulk-delete', {
            agent: props.agent.id,
            website: currentWebsite.value.id
        }), {
            link_ids: Array.from(dialogSelectedLinks.value)
        });

        const deletedCount = dialogSelectedLinks.value.size;

        // Clear selection
        dialogSelectedLinks.value.clear();

        toast.success(`Deleted ${deletedCount} link${deletedCount !== 1 ? 's' : ''} successfully.`);

        // Reload current page with search query
        await fetchDialogLinks(currentWebsite.value.id, dialogLinksPagination.value.currentPage, dialogSearchQuery.value);

        // Refresh usage data
        await fetchUsageData();
    } catch (error: any) {
        console.error('Failed to delete links:', error);
        toast.error(error.response?.data?.message || 'Failed to delete links.');
    }
};

// Retrain individual link
const retrainDialogLink = async (linkId: number) => {
    if (!currentWebsite.value) return;

    try {
        // Optimistically update the status to pending in the UI
        const linkIndex = dialogLinks.value.findIndex(link => link.id === linkId);
        if (linkIndex !== -1) {
            const updatedLinks = [...dialogLinks.value];
            updatedLinks[linkIndex] = {
                ...updatedLinks[linkIndex],
                status: 'pending'
            };
            dialogLinks.value = updatedLinks;
        }

        // Also update in discovered_links if present
        const sourceIndex = webSources.value.findIndex(source => source.id === currentWebsite.value!.id);
        if (sourceIndex !== -1 && webSources.value[sourceIndex].discovered_links) {
            const discoveredLinks = webSources.value[sourceIndex].discovered_links;
            if (discoveredLinks) {
                const discLinkIndex = discoveredLinks.findIndex(link => link.id === linkId);
                if (discLinkIndex !== -1) {
                    const updatedLinks = [...discoveredLinks];
                    updatedLinks[discLinkIndex] = {
                        ...updatedLinks[discLinkIndex],
                        status: 'pending'
                    };

                    const updatedSources = [...webSources.value];
                    updatedSources[sourceIndex] = {
                        ...updatedSources[sourceIndex],
                        discovered_links: updatedLinks
                    };
                    webSources.value = updatedSources;
                }
            }
        }

        await axios.post(route('agents.sources.websites.links.retrain', {
            agent: props.agent.id,
            website: currentWebsite.value.id,
            link: linkId
        }));

        toast.success('Link retraining started successfully');
    } catch (error: any) {
        console.error('Failed to retrain link:', error);
        toast.error(error.response?.data?.message || 'Failed to retrain link');

        // Revert status on error
        const linkIndex = dialogLinks.value.findIndex(link => link.id === linkId);
        if (linkIndex !== -1) {
            const updatedLinks = [...dialogLinks.value];
            updatedLinks[linkIndex] = {
                ...updatedLinks[linkIndex],
                status: 'ingested'
            };
            dialogLinks.value = updatedLinks;
        }
    }
};

// Open retrain dialog
const openRetrainDialog = (websiteId: number) => {
    const website = webSources.value.find(s => s.id === websiteId);
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
        await axios.post(route('agents.sources.website.train', {
            agent: props.agent.id,
            website: websiteToRetrain.value.id
        }), {
            recrawl: reCrawlNewLinks.value
        });

        toast.success('Website retraining started successfully');

        // Refresh the sources list
        await fetchWebSources();
    } catch (error: any) {
        console.error('Failed to retrain website:', error);
        toast.error(error.response?.data?.message || 'Failed to retrain website');
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
            title="Smart Monitoring"
            message="Your websites will be periodically checked for changes and automatically re-crawled when necessary to keep your agent's knowledge up-to-date."
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
                            Crawler
                        </CardTitle>
                        <CardDescription>
                            Fetch links from websites to crawl and include in your agent's knowledge base.
                        </CardDescription>
                    </div>
                    <Button variant="outline" size="sm" @click="settingsExpanded = !settingsExpanded">
                        <Settings class="h-4 w-4 mr-2" />
                        Settings
                        <ChevronDown v-if="!settingsExpanded" class="h-4 w-4 ml-1" />
                        <ChevronUp v-else class="h-4 w-4 ml-1" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent>

                <!-- Link Usage Info -->
                <div v-if="usageData" class="mb-4 p-3 bg-muted/20 rounded-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">
                            {{ usageData.links.used }} / {{ usageData.links.limit }} links used
                        </span>
                        <span class="text-sm font-medium" :class="usageData.links.remaining <= 0 ? 'text-red-600' : 'text-green-600'">
                            {{ usageData.links.remaining }} remaining
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-xs mt-1">
                        <span v-if="usageData.links.remaining <= 0" class="text-red-600 font-medium">
                            Upgrade required to train more links
                        </span>
                        <span v-else-if="(usageData.links.used / usageData.links.limit) >= 0.9" class="text-yellow-600 font-medium">
                            Consider upgrading
                        </span>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex gap-2">
                        <!-- URL Input with Integrated Protocol Dropdown -->
                        <div class="flex-1 relative">
                            <div class="flex">
                                <!-- Protocol Dropdown integrated into input -->
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button class="flex items-center px-3 py-1 bg-muted border border-input border-r-0 rounded-l-md text-sm font-mono hover:bg-muted/80 focus:outline-none focus:ring-1 focus:ring-ring focus:ring-offset-0 h-9">
                                            {{ selectedProtocol }}
                                            <ChevronDown class="h-3 w-3 ml-1 text-muted-foreground" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <DropdownMenuItem @click="updateProtocol('https://')">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                                https://
                                            </div>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="updateProtocol('http://')">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
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
                                        'rounded-l-none border-l-0 focus:border-l-0 focus:ring-offset-0',
                                        { 'border-red-500': websiteForm.errors.url }
                                    ]"
                                    class="flex-1"
                                />
                            </div>
                            <InputError :message="websiteForm.errors.url" />
                        </div>

                        <!-- Add Button -->
                        <Button
                            @click="addWebsite"
                            :disabled="!isValidUrl || websiteForm.processing"
                        >
                            <LoaderCircle v-if="websiteForm.processing" class="h-4 w-4 mr-2 animate-spin" />
                            <Globe v-else class="h-4 w-4 mr-2" />
                            {{ websiteForm.processing ? 'Fetching...' : 'Fetch Links' }}
                        </Button>
                    </div>

                    <!-- Collapsible Settings Section -->
                    <Collapsible v-model:open="settingsExpanded">
                        <CollapsibleContent>
                            <div class="border rounded-lg p-4 bg-muted/20 space-y-4">
                                <div class="mb-3">
                                    <h3 class="text-sm font-semibold">Crawl Settings</h3>
                                </div>

                                <!-- Include Paths -->
                                <div class="space-y-2">
                                    <Label for="include-paths">Include only paths</Label>
                                    <Input
                                        id="include-paths"
                                        type="text"
                                        placeholder="Ex: blog/*, dev/*"
                                        v-model="crawlSettings.include_paths"
                                        :class="{ 'border-red-500': websiteForm.errors.include_paths }"
                                        class="text-sm"
                                    />
                                    <InputError :message="websiteForm.errors.include_paths" />
                                    <div class="text-xs text-muted-foreground">
                                        Use wildcards (*) to match patterns. Leave empty to include all paths.
                                    </div>
                                </div>

                                <!-- Exclude Paths -->
                                <div class="space-y-2">
                                    <Label for="exclude-paths">Exclude paths</Label>
                                    <Input
                                        id="exclude-paths"
                                        type="text"
                                        placeholder="Ex: blog/*, dev/*"
                                        class="text-sm"
                                        :class="{ 'border-red-500': websiteForm.errors.exclude_paths }"
                                        v-model="crawlSettings.exclude_paths"
                                    />
                                    <InputError :message="websiteForm.errors.exclude_paths" />
                                    <div class="text-xs text-muted-foreground">
                                        Use wildcards (*) to match patterns. These paths will be skipped.
                                    </div>
                                </div>

                                <!-- Switches -->
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-0.5">
                                            <Label for="slow-crawling">Slow Crawling</Label>
                                            <div class="text-xs text-muted-foreground">
                                                Uses longer delays for more respectful crawling
                                            </div>
                                        </div>
                                        <Switch
                                            id="slow-crawling"
                                            :model-value="crawlSettings.slow_crawling"
                                            @update:model-value="(value) => { crawlSettings.slow_crawling = Boolean(value); }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- General error message for any other form errors -->
                    <div v-if="websiteForm.hasErrors && !websiteForm.errors.url && !websiteForm.errors.include_paths && !websiteForm.errors.exclude_paths" class="space-y-1">
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
                    Crawling...
                </CardTitle>
                <CardDescription>
                    {{ crawlingSources.length }} website{{ crawlingSources.length !== 1 ? 's' : '' }} currently being crawled.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <template v-for="source in crawlingSources" :key="source.id">
                        <div class="flex items-center justify-between border rounded-lg p-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <Globe class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-medium truncate">{{ source.name || getDomainFromUrl(source.url) }}</span>
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
                                                    <p>{{ source.status === 'crawling' ? 'Currently crawling...' :
                                                         source.status === 'pending' ? 'Ready to crawl' :
                                                         'Processing' }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        <a :href="source.url" target="_blank" class="hover:underline flex items-center gap-1">
                                            {{ source.url }}
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                        <!-- Crawl criteria message -->
                                        <div v-if="getCrawlCriteriaMessage(source)" class="text-xs text-blue-600 font-medium mt-1">
                                            {{ getCrawlCriteriaMessage(source) }}
                                        </div>
                                        <div class="mt-1">
                                            <span>
                                                Added {{ formatDate(source.created_at) }}
                                            </span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                size="sm"
                                                variant="ghost"
                                                @click="confirmDeleteSource(source)"
                                            >
                                                <span class="sr-only">Remove source</span>
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Delete web source</p>
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
                    Crawled
                </CardTitle>
                <CardDescription>
                    {{ crawledSources.length }} website{{ crawledSources.length !== 1 ? 's' : '' }} crawled and ready for training.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- Selection controls -->
                <div class="flex items-center justify-between mb-4 p-3 bg-muted/30 rounded-lg">
                    <div class="flex items-center gap-3">
                        <Checkbox
                            :model-value="allCrawledWebsitesSelected"
                            :indeterminate="someCrawledWebsitesSelected"
                            @update:model-value="toggleSelectAllWebsites"
                            id="select-all-websites"
                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                        />
                        <Label for="select-all-websites" class="text-sm font-medium cursor-pointer select-none">
                            Select All Websites ({{ crawledSources.length }})
                        </Label>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted-foreground">
                            <template v-if="selectedWebsiteCount > 0">
                                {{ selectedWebsiteCount }} selected ({{ selectedWebsitesTotalLinks }} link{{ selectedWebsitesTotalLinks !== 1 ? 's' : '' }})
                            </template>
                            <template v-else>
                                No websites selected
                            </template>
                        </span>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        @click="trainSelectedWebsites"
                                        :disabled="!canTrain || isTraining"
                                        size="sm"

                                    >
                                        <LoaderCircle v-if="isTraining" class="h-4 w-4 mr-2 animate-spin" />
                                        <Database v-else class="h-4 w-4 mr-2" />
                                        Train
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent v-if="!canTrain && selectedWebsiteCount > 0 && usageData">
                                    <p>Selected links ({{ selectedWebsitesTotalLinks }}) exceed your remaining quota ({{ usageData.links.remaining }})</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>


                <div class="space-y-2">
                    <template v-for="source in crawledSources" :key="source.id">
                        <Collapsible v-if="source.urls_count && source.urls_count > 0" v-slot="{ open }" class="border rounded-lg" @update:open="(isOpen) => handleCollapsibleToggle(source.id, isOpen)">
                            <div class="flex items-center justify-between p-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1" @click.stop>
                                    <Checkbox
                                        :model-value="selectedWebsites.has(source.id)"
                                        @update:model-value="(checked) => toggleWebsiteSelection(source.id, checked)"
                                        :id="`website-${source.id}`"
                                        class="data-[state=checked]:bg-primary data-[state=checked]:border-primary flex-shrink-0"
                                    />
                                    <Globe class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-medium truncate">{{ source.name || getDomainFromUrl(source.url) }}</span>

                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Badge variant="secondary" class="text-xs">
                                                            {{ source.urls_count }} links
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
                                                        <Badge variant="outline" class="text-xs border-yellow-500 text-yellow-700 dark:text-yellow-400">
                                                            <AlertCircle class="h-3 w-3 mr-1" />
                                                            {{ usageData.links.remaining <= 0 ? 'Upgrade required' : 'Exceeds quota' }}
                                                        </Badge>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p v-if="usageData.links.remaining <= 0">
                                                            You have no links remaining in your plan. Please upgrade to train this website.
                                                        </p>
                                                        <p v-else>
                                                            This website has {{ source.urls_count }} links, which would exceed your remaining {{ usageData.links.remaining }} link{{ usageData.links.remaining !== 1 ? 's' : '' }}. Deselect other websites first or upgrade your plan.
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            <a :href="source.url" target="_blank" class="hover:underline flex items-center gap-1">
                                                {{ source.url }}
                                                <ExternalLink class="h-3 w-3" />
                                            </a>
                                            <!-- Crawl criteria message -->
                                            <div v-if="getCrawlCriteriaMessage(source)" class="text-xs text-blue-600 font-medium mt-1">
                                                {{ getCrawlCriteriaMessage(source) }}
                                            </div>
                                            <div class="mt-1">
                                                <span>
                                                    Added {{ formatDate(source.created_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <!-- Collapsible Trigger for discovered links -->
                                    <CollapsibleTrigger asChild>
                                        <Button
                                            class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground flex-shrink-0"
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <span class="sr-only">{{ open ? 'Collapse' : 'Expand' }} discovered links</span>
                                            <ChevronUp v-if="open" class="h-4 w-4" />
                                            <ChevronDown v-else class="h-4 w-4" />
                                        </Button>
                                    </CollapsibleTrigger>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="confirmDeleteSource(source)"
                                                >
                                                    <span class="sr-only">Remove source</span>
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Delete web source</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>

                            <!-- Collapsible Content for discovered links -->
                            <CollapsibleContent>
                                <div class="border-t bg-muted/20 p-3">
                                    <!-- Header with search and controls -->
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="text-sm font-medium text-muted-foreground">
                                            Discovered Links
                                            <span v-if="linkSearchQueries.get(source.id)?.trim()" class="text-xs text-blue-600 ml-1">(filtered)</span>
                                        </div>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="deleteSelectedLinks(source.id)"
                                            :disabled="(selectedLinks.get(source.id)?.size || 0) === 0"
                                            class="h-7 text-xs"
                                        >
                                            <Trash2 class="h-3 w-3 mr-1" />
                                            Delete selected links
                                        </Button>
                                    </div>

                                    <!-- Search box -->
                                    <div v-if="source.discovered_links" class="mb-3">
                                        <Input
                                            :value="linkSearchQueries.get(source.id) || ''"
                                            @input="(e: Event) => updateSearchQuery(source.id, (e.target as HTMLInputElement).value)"
                                            placeholder="Search links..."
                                            class="h-8 text-xs"
                                        />
                                    </div>

                                    <!-- Select all checkbox -->
                                    <div v-if="source.discovered_links" class="flex items-center gap-2 mb-3 p-2 bg-background rounded border">
                                        <Checkbox
                                            :model-value="getSelectionState(source.id, source.discovered_links).allSelected"
                                            :indeterminate="getSelectionState(source.id, source.discovered_links).someSelected"
                                            @update:model-value="(checked) => toggleSelectAllLinks(source.id, source.discovered_links || [], checked)"
                                            :id="`select-all-${source.id}`"
                                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                        />
                                        <Label :for="`select-all-${source.id}`" class="text-xs font-medium cursor-pointer select-none">
                                            Select All on Page ({{ source.discovered_links.length }})
                                        </Label>
                                    </div>

                                    <!-- Links list with infinite scroll -->
                                    <ScrollArea class="h-72 w-full rounded-md border">
                                        <div class="p-4">
                                            <!-- Initial loading state -->
                                            <div v-if="!source.discovered_links || linksPagination.get(source.id)?.isLoading" class="flex items-center justify-center py-8">
                                                <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
                                                <span class="ml-2 text-sm text-muted-foreground">Loading links...</span>
                                            </div>

                                            <!-- Links loaded -->
                                            <div v-else class="space-y-2">
                                                <div
                                                    v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                    :key="link.link"
                                                    class="flex items-start gap-2 p-2 bg-background rounded border text-xs hover:bg-muted/50 transition-colors group"
                                                >
                                                    <!-- Checkbox -->
                                                    <Checkbox
                                                        :model-value="(selectedLinks.get(source.id) || new Set()).has(link.id)"
                                                        @update:model-value="(checked) => toggleLinkSelection(source.id, link.id, checked)"
                                                        :id="`link-${source.id}-${link.id}`"
                                                        class="mt-0.5 data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                                    />
                                                    <ExternalLink class="h-3 w-3 mt-0.5 text-muted-foreground flex-shrink-0" />
                                                    <div class="min-w-0 flex-1">
                                                        <a
                                                            :href="link.link"
                                                            target="_blank"
                                                            class="text-foreground hover:text-primary hover:underline truncate block text-sm"
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
                                                        class="h-6 w-6 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-destructive hover:text-destructive/80"
                                                    >
                                                        <Trash2 class="h-3 w-3" />
                                                    </Button>
                                                </div>

                                                <!-- No results message -->
                                                <div v-if="getFilteredLinks(source.id, source.discovered_links).length === 0" class="text-center py-4 text-muted-foreground text-xs">
                                                    No links match your search
                                                </div>
                                            </div>
                                        </div>
                                    </ScrollArea>

                                    <!-- Pagination Controls -->
                                    <div v-if="linksPagination.get(source.id)" class="flex items-center justify-between mt-3 px-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="loadPrevPage(source.id)"
                                            :disabled="!linksPagination.get(source.id)?.hasPrevPage || linksPagination.get(source.id)?.isLoading"
                                            class="h-8"
                                        >
                                            <ChevronRight class="h-4 w-4 rotate-180 mr-1" />
                                            Previous
                                        </Button>
                                        <span class="text-xs text-muted-foreground">
                                            Page {{ linksPagination.get(source.id)?.currentPage || 1 }}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="loadNextPage(source.id)"
                                            :disabled="!linksPagination.get(source.id)?.hasNextPage || linksPagination.get(source.id)?.isLoading"
                                            class="h-8"
                                        >
                                            Next
                                            <ChevronRight class="h-4 w-4 ml-1" />
                                        </Button>
                                    </div>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Sources without links (simplified display) -->
                        <div v-else class="border rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <Checkbox
                                        :model-value="selectedWebsites.has(source.id)"
                                        @update:model-value="(checked) => toggleWebsiteSelection(source.id, checked)"
                                        :id="`website-simple-${source.id}`"
                                        class="data-[state=checked]:bg-primary data-[state=checked]:border-primary flex-shrink-0"
                                    />
                                    <Globe class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-medium truncate">{{ source.name || getDomainFromUrl(source.url) }}</span>
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
                                        <div class="text-xs text-muted-foreground">
                                            <a :href="source.url" target="_blank" class="hover:underline flex items-center gap-1">
                                                {{ source.url }}
                                                <ExternalLink class="h-3 w-3" />
                                            </a>
                                            <!-- Crawl criteria message -->
                                            <div v-if="getCrawlCriteriaMessage(source)" class="text-xs text-blue-600 font-medium mt-1">
                                                {{ getCrawlCriteriaMessage(source) }}
                                            </div>
                                            <div class="mt-1">
                                                <span>
                                                    Added {{ formatDate(source.created_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="confirmDeleteSource(source)"
                                                >
                                                    <span class="sr-only">Remove source</span>
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Delete web source</p>
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
                    Training
                </CardTitle>
                <CardDescription>
                    {{ trainingSources.length }} website{{ trainingSources.length !== 1 ? 's' : '' }} currently being trained.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <template v-for="source in trainingSources" :key="source.id">
                        <div class="flex items-center justify-between border rounded-lg p-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <Globe class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-medium truncate">{{ source.name || getDomainFromUrl(source.url) }}</span>
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        <a :href="source.url" target="_blank" class="hover:underline flex items-center gap-1">
                                            {{ source.url }}
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                        <div class="mt-1">
                                            <span>
                                                Added {{ formatDate(source.created_at) }}
                                            </span>
                                        </div>
                                        
                                        <!-- Progress Bar -->
                                        <IngestionProgressBar
                                            v-if="source.status === 'ingesting'"
                                            :progress="source.progress ?? 0"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <!-- View Links Button -->
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground flex-shrink-0"
                                                size="sm"
                                                variant="ghost"
                                                @click="openLinksDialog(source)"
                                            >
                                                <Link2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>View all links</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                size="sm"
                                                variant="ghost"
                                                @click="confirmDeleteSource(source)"
                                            >
                                                <span class="sr-only">Remove source</span>
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Delete website (will cancel training)</p>
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
                            Trained
                        </CardTitle>
                        <CardDescription>
                            Websites trained and available to your agent. {{ completedSources.length }} website{{ completedSources.length !== 1 ? 's' : '' }} in total.
                        </CardDescription>
                    </div>
                    <Button @click="openAttachWebsiteDialog" variant="outline" size="sm" class="flex items-center gap-2">
                        <Link2 class="h-4 w-4" />
                        Link Website
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div v-if="isLoadingSources && isInitialLoad" class="p-4 text-center text-muted-foreground">
                        <LoaderCircle class="mx-auto h-6 w-6 animate-spin mb-2" />
                        Loading web sources...
                    </div>
                    <div v-else-if="completedSources.length === 0" class="p-4 text-center text-muted-foreground">
                        No websites in knowledge base yet
                    </div>
                    <template v-else>
                        <ScrollArea v-if="completedSources.length > 5" class="h-[400px] pr-4">
                            <div class="space-y-2">
                                <template v-for="source in completedSources" :key="source.id">
                                    <div class="border rounded-lg">
                                            <div class="flex items-center justify-between p-3">
                                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                                    <Globe class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <span class="font-medium truncate">{{ source.name || getDomainFromUrl(source.url) }}</span>
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
                                                        <div class="text-xs text-muted-foreground">
                                                            <a :href="source.url" target="_blank" class="hover:underline flex items-center gap-1">
                                                                {{ source.url }}
                                                                <ExternalLink class="h-3 w-3" />
                                                            </a>
                                                            <div class="mt-1">
                                                                <span>
                                                                    Added {{ formatDate(source.created_at) }}
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
                                                                    class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground flex-shrink-0"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    @click="openLinksDialog(source)"
                                                                >
                                                                    <Link2 class="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>View all links</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <!-- Retrain Website Button -->
                                                    <TooltipProvider v-if="showRetrainButton">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    class="h-8 w-8 p-0 text-green-600 hover:text-green-700 hover:bg-green-50 flex-shrink-0"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    @click="openRetrainDialog(source.id)"
                                                                >
                                                                    <RefreshCw class="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Retrain entire website</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <!-- Expand/Collapse button for completed sources with links -->
                                                    <Button
                                                        v-if="source.discovered_links && source.discovered_links.length > 0"
                                                        class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground flex-shrink-0"
                                                        size="sm"
                                                        variant="ghost"
                                                        @click="toggleSourceExpansion(source.id)"
                                                    >
                                                        <span class="sr-only">{{ expandedSources.get(source.id) ? 'Collapse' : 'Expand' }} discovered links</span>
                                                        <ChevronDown v-if="expandedSources.get(source.id)" class="h-4 w-4" />
                                                        <ChevronRight v-else class="h-4 w-4" />
                                                    </Button>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    @click="confirmDeleteSource(source)"
                                                                >
                                                                    <span class="sr-only">Remove source</span>
                                                                    <Trash2 class="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Delete web source</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                            </div>

                                            <!-- Collapsible content for discovered links -->
                                            <div v-if="source.discovered_links && source.discovered_links.length > 0 && expandedSources.get(source.id)" class="debug-collapsible">
                                                <div class="border-t bg-muted/20">
                                                    <div class="p-3">
                                                        <!-- Header with search and controls -->
                                                        <div class="flex items-center justify-between mb-3">
                                                            <div class="text-sm font-medium text-muted-foreground">
                                                                Discovered Links
                                                                <span v-if="linkSearchQueries.get(source.id)?.trim()" class="text-xs text-blue-600 ml-1">(filtered)</span>
                                                            </div>
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                @click="deleteSelectedLinks(source.id)"
                                                                :disabled="(selectedLinks.get(source.id)?.size || 0) === 0"
                                                                class="h-7 text-xs"
                                                            >
                                                                <Trash2 class="h-3 w-3 mr-1" />
                                                                Delete selected links
                                                            </Button>
                                                        </div>

                                                        <!-- Search box -->
                                                        <div class="mb-3">
                                                            <Input
                                                                :value="linkSearchQueries.get(source.id) || ''"
                                                                @input="(e: Event) => updateSearchQuery(source.id, (e.target as HTMLInputElement).value)"
                                                                placeholder="Search links..."
                                                                class="h-8 text-xs"
                                                            />
                                                        </div>

                                                        <!-- Select all checkbox -->
                                                        <div class="flex items-center gap-2 mb-3 p-2 bg-background rounded border">
                                                            <Checkbox
                                                                :model-value="getSelectionState(source.id, source.discovered_links).allSelected"
                                                                :indeterminate="getSelectionState(source.id, source.discovered_links).someSelected"
                                                                @update:model-value="(checked) => toggleSelectAllLinks(source.id, source.discovered_links || [], checked)"
                                                                id="`select-all-${source.id}`"
                                                                class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                                            />
                                                            <Label :for="`select-all-${source.id}`" class="text-xs font-medium cursor-pointer select-none">
                                                                Select All on Page ({{ source.discovered_links.length }})
                                                            </Label>
                                                        </div>

                                                        <!-- Links list with infinite scroll -->
                                                        <ScrollArea class="h-72 w-full rounded-md border">
                                                            <div class="p-4">
                                                                <!-- Loading state -->
                                                                <div v-if="linksPagination.get(source.id)?.isLoading" class="flex items-center justify-center py-8">
                                                                    <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
                                                                    <span class="ml-2 text-sm text-muted-foreground">Loading links...</span>
                                                                </div>

                                                                <div v-else class="space-y-2">
                                                                    <div
                                                                        v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                                        :key="link.link"
                                                                        class="flex items-start gap-2 p-2 bg-background rounded border text-xs hover:bg-muted/50 transition-colors group"
                                                                    >
                                                                        <Checkbox
                                                                            :model-value="(selectedLinks.get(source.id) || new Set()).has(link.id)"
                                                                            @update:model-value="(checked) => toggleLinkSelection(source.id, link.id, checked)"
                                                                            :id="`link-${source.id}-${link.id}`"
                                                                            class="mt-0.5 data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                                                        />
                                                                        <ExternalLink class="h-3 w-3 mt-0.5 text-muted-foreground flex-shrink-0" />
                                                                        <div class="min-w-0 flex-1">
                                                                            <a
                                                                                :href="link.link"
                                                                                target="_blank"
                                                                                class="text-foreground hover:text-primary hover:underline truncate block text-sm"
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
                                                                            class="h-6 w-6 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-destructive hover:text-destructive/80"
                                                                        >
                                                                            <Trash2 class="h-3 w-3" />
                                                                        </Button>
                                                                    </div>

                                                                    <!-- No results message -->
                                                                    <div v-if="getFilteredLinks(source.id, source.discovered_links || []).length === 0" class="text-center py-4 text-muted-foreground text-xs">
                                                                        No links match your search
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </ScrollArea>

                                                        <!-- Pagination Controls -->
                                                        <div v-if="linksPagination.get(source.id)" class="flex items-center justify-between mt-3 px-2">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                @click="loadPrevPage(source.id)"
                                                                :disabled="!linksPagination.get(source.id)?.hasPrevPage || linksPagination.get(source.id)?.isLoading"
                                                                class="h-8"
                                                            >
                                                                <ChevronRight class="h-4 w-4 rotate-180 mr-1" />
                                                                Previous
                                                            </Button>
                                                            <span class="text-xs text-muted-foreground">
                                                                Page {{ linksPagination.get(source.id)?.currentPage || 1 }}
                                                            </span>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                @click="loadNextPage(source.id)"
                                                                :disabled="!linksPagination.get(source.id)?.hasNextPage || linksPagination.get(source.id)?.isLoading"
                                                                class="h-8"
                                                            >
                                                                Next
                                                                <ChevronRight class="h-4 w-4 ml-1" />
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
                                <div class="border rounded-lg">
                                        <div class="flex items-center justify-between p-3">
                                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                                <Globe class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="font-medium truncate">{{ source.name || getDomainFromUrl(source.url) }}</span>
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
                                                    <div class="text-xs text-muted-foreground">
                                                        <a :href="source.url" target="_blank" class="hover:underline flex items-center gap-1">
                                                            {{ source.url }}
                                                            <ExternalLink class="h-3 w-3" />
                                                        </a>
                                                        <div class="mt-1">
                                                            <span>
                                                                Added {{ formatDate(source.created_at) }}
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
                                                                class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground flex-shrink-0"
                                                                size="sm"
                                                                variant="ghost"
                                                                @click="openLinksDialog(source)"
                                                            >
                                                                <Link2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>View all links</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <!-- Retrain Website Button -->
                                                <TooltipProvider v-if="showRetrainButton">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                class="h-8 w-8 p-0 text-green-600 hover:text-green-700 hover:bg-green-50 flex-shrink-0"
                                                                size="sm"
                                                                variant="ghost"
                                                                @click="openRetrainDialog(source.id)"
                                                            >
                                                                <RefreshCw class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>Retrain entire website</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <!-- Expand/Collapse button for completed sources with links -->
                                                <Button
                                                    v-if="source.discovered_links && source.discovered_links.length > 0"
                                                    class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground flex-shrink-0"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="toggleSourceExpansion(source.id)"
                                                >
                                                    <span class="sr-only">{{ expandedSources.get(source.id) ? 'Collapse' : 'Expand' }} discovered links</span>
                                                    <ChevronDown v-if="expandedSources.get(source.id)" class="h-4 w-4" />
                                                    <ChevronRight v-else class="h-4 w-4" />
                                                </Button>
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                class="h-8 w-8 p-0 text-destructive hover:text-destructive/80 flex-shrink-0"
                                                                size="sm"
                                                                variant="ghost"
                                                                @click="confirmDeleteSource(source)"
                                                            >
                                                                <span class="sr-only">Remove source</span>
                                                                <Trash2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>Delete web source</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                        </div>

                                        <!-- Collapsible content for discovered links -->
                                        <div v-if="source.discovered_links && source.discovered_links.length > 0 && expandedSources.get(source.id)">
                                            <div class="border-t bg-muted/20">
                                                <div class="p-3">
                                                    <!-- Header with search and controls -->
                                                    <div class="flex items-center justify-between mb-3">
                                                        <div class="text-sm font-medium text-muted-foreground">
                                                            Discovered Links
                                                            <span v-if="linkSearchQueries.get(source.id)?.trim()" class="text-xs text-blue-600 ml-1">(filtered)</span>
                                                        </div>
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            @click="deleteSelectedLinks(source.id)"
                                                            :disabled="(selectedLinks.get(source.id)?.size || 0) === 0"
                                                            class="h-7 text-xs"
                                                        >
                                                            <Trash2 class="h-3 w-3 mr-1" />
                                                            Delete selected links
                                                        </Button>
                                                    </div>

                                                    <!-- Search box -->
                                                    <div class="mb-3">
                                                        <Input
                                                            :value="linkSearchQueries.get(source.id) || ''"
                                                            @input="(e: Event) => updateSearchQuery(source.id, (e.target as HTMLInputElement).value)"
                                                            placeholder="Search links..."
                                                            class="h-8 text-xs"
                                                        />
                                                    </div>

                                                    <!-- Select all checkbox -->
                                                    <div class="flex items-center gap-2 mb-3 p-2 bg-background rounded border">
                                                        <Checkbox
                                                            :model-value="getSelectionState(source.id, source.discovered_links).allSelected"
                                                            :indeterminate="getSelectionState(source.id, source.discovered_links).someSelected"
                                                            @update:model-value="(checked) => toggleSelectAllLinks(source.id, source.discovered_links || [], checked)"
                                                            :id="`select-all-${source.id}`"
                                                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                                        />
                                                        <Label :for="`select-all-${source.id}`" class="text-xs font-medium cursor-pointer select-none">
                                                            Select All on Page ({{ source.discovered_links.length }})
                                                        </Label>
                                                    </div>

                                                    <!-- Links list -->
                                                    <ScrollArea class="h-72 w-full rounded-md border">
                                                        <div class="p-4">
                                                            <!-- Loading state -->
                                                            <div v-if="linksPagination.get(source.id)?.isLoading" class="flex items-center justify-center py-8">
                                                                <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
                                                                <span class="ml-2 text-sm text-muted-foreground">Loading links...</span>
                                                            </div>

                                                            <div v-else class="space-y-2">
                                                                <div
                                                                    v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                                    :key="link.link"
                                                                    class="flex items-start gap-2 p-2 bg-background rounded border text-xs hover:bg-muted/50 transition-colors group"
                                                                >
                                                                    <Checkbox
                                                                        :model-value="(selectedLinks.get(source.id) || new Set()).has(link.id)"
                                                                        @update:model-value="(checked) => toggleLinkSelection(source.id, link.id, checked)"
                                                                        :id="`link-${source.id}-${link.id}`"
                                                                        class="mt-0.5 data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                                                    />
                                                                    <ExternalLink class="h-3 w-3 mt-0.5 text-muted-foreground flex-shrink-0" />
                                                                    <div class="min-w-0 flex-1">
                                                                        <a
                                                                            :href="link.link"
                                                                            target="_blank"
                                                                            class="text-foreground hover:text-primary hover:underline truncate block text-sm"
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
                                                                        class="h-6 w-6 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-destructive hover:text-destructive/80"
                                                                    >
                                                                        <Trash2 class="h-3 w-3" />
                                                                    </Button>
                                                                </div>

                                                                <!-- No results message -->
                                                                <div v-if="getFilteredLinks(source.id, source.discovered_links).length === 0" class="text-center py-4 text-muted-foreground text-xs">
                                                                    No links match your search
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </ScrollArea>

                                                    <!-- Pagination Controls -->
                                                    <div v-if="linksPagination.get(source.id)" class="flex items-center justify-between mt-3 px-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            @click="loadPrevPage(source.id)"
                                                            :disabled="!linksPagination.get(source.id)?.hasPrevPage || linksPagination.get(source.id)?.isLoading"
                                                            class="h-8"
                                                        >
                                                            <ChevronRight class="h-4 w-4 rotate-180 mr-1" />
                                                            Previous
                                                        </Button>
                                                        <span class="text-xs text-muted-foreground">
                                                            Page {{ linksPagination.get(source.id)?.currentPage || 1 }}
                                                        </span>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            @click="loadNextPage(source.id)"
                                                            :disabled="!linksPagination.get(source.id)?.hasNextPage || linksPagination.get(source.id)?.isLoading"
                                                            class="h-8"
                                                        >
                                                            Next
                                                            <ChevronRight class="h-4 w-4 ml-1" />
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
                        This website is used in <strong>{{ sourceToDelete.agents_count }} agents</strong>. 
                        You can unlink it from this agent or permanently delete it from all agents.
                    </span>
                    <span v-else-if="sourceToDelete.status === 'ingesting' || sourceToDelete.status === 'crawling'">
                        This website is currently being trained. The training process will be canceled and all data will be permanently removed from your agent's knowledge base. This action cannot be undone.
                    </span>
                    <span v-else>
                        This will permanently remove this web source from your agent's knowledge base. All crawled pages will be deleted. This action cannot be undone.
                    </span>
                </DialogDescription>
            </DialogHeader>
            <div class="flex items-center justify-end gap-2 mt-4">
                <Button 
                    variant="outline" 
                    @click="deleteDialogOpen = false"
                    :disabled="isUnlinking || isDeletingSource"
                >
                    Cancel
                </Button>
                <Button 
                    v-if="isWebsiteShared(sourceToDelete)" 
                    variant="secondary" 
                    @click="unlinkWebsite"
                    :disabled="isUnlinking || isDeletingSource"
                    class="bg-orange-500 hover:bg-orange-600 text-white disabled:opacity-50"
                >
                    <LoaderCircle v-if="isUnlinking" class="h-4 w-4 mr-2 animate-spin" />
                    <Unlink v-else class="h-4 w-4 mr-2" />
                    {{ isUnlinking ? 'Unlinking...' : 'Unlink' }}
                </Button>
                <Button 
                    variant="destructive" 
                    @click="deleteWebSource"
                    :disabled="isUnlinking || isDeletingSource"
                >
                    <LoaderCircle v-if="isDeletingSource" class="h-4 w-4 mr-2 animate-spin" />
                    <Trash2 v-else class="h-4 w-4 mr-2" />
                    <span v-if="isDeletingSource">Deleting...</span>
                    <span v-else>{{ isWebsiteShared(sourceToDelete) ? 'Delete permanently' : 'Delete' }}</span>
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Retrain Website Dialog -->
    <Dialog v-model:open="retrainDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Retrain Website</DialogTitle>
                <DialogDescription>
                    Retrain {{ websiteToRetrain?.name || getDomainFromUrl(websiteToRetrain?.url || '') }} to update your agent's knowledge with the latest content.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div class="flex items-start gap-3 p-3 rounded-lg border bg-muted/50">
                    <Checkbox
                        :id="'recrawl-checkbox'"
                        :checked="reCrawlNewLinks"
                        @update:checked="(checked: boolean) => reCrawlNewLinks = checked"
                    />
                    <div class="grid gap-1.5 leading-none">
                        <Label
                            :for="'recrawl-checkbox'"
                            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                        >
                            Re-crawl and train new links
                        </Label>
                        <p class="text-sm text-muted-foreground">
                            If checked, the website will be crawled again to discover and train any new pages that have been added since the last crawl.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 justify-end">
                <Button
                    variant="outline"
                    @click="retrainDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    @click="retrainEntireWebsite"
                >
                    Start Retraining
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Links Dialog Modal -->
    <Dialog v-model:open="linksDialogOpen" @update:open="(open) => !open && closeLinksDialog()">
        <DialogContent class="max-w-4xl h-[80vh] flex flex-col">
            <DialogHeader>
                <DialogTitle>Website Links</DialogTitle>
                <DialogDescription>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span v-if="currentWebsite">View and manage links from {{ currentWebsite.name || getDomainFromUrl(currentWebsite.url) }}</span>
                        <span v-if="dialogLinksPagination.total > 0" class="font-medium text-foreground">
                            • {{ dialogLinksPagination.total }} link{{ dialogLinksPagination.total !== 1 ? 's' : '' }} total
                        </span>
                    </div>
                </DialogDescription>
            </DialogHeader>

            <!-- Search Box and Controls -->
            <div class="space-y-3 mb-4">
                <Input
                    :value="dialogSearchQuery"
                    @input="(e: Event) => updateDialogSearchQuery((e.target as HTMLInputElement).value)"
                    placeholder="Search links..."
                    class="h-9"
                />
                <p v-if="dialogSearchQuery.trim()" class="text-xs text-blue-600">
                    Showing filtered results
                </p>

                <!-- Select All and Bulk Delete -->
                <div v-if="dialogLinks.length > 0" class="flex items-center justify-between p-2 bg-muted/30 rounded border">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :model-value="dialogAllLinksSelected"
                            :indeterminate="dialogSomeLinksSelected"
                            @update:model-value="toggleSelectAllDialogLinks"
                            id="select-all-dialog-links"
                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                        />
                        <Label for="select-all-dialog-links" class="text-xs font-medium cursor-pointer select-none">
                            Select All on Page ({{ dialogLinks.length }})
                        </Label>
                    </div>
                    <Button
                        variant="destructive"
                        size="sm"
                        @click="deleteSelectedDialogLinks"
                        :disabled="dialogSelectedLinks.size === 0"
                        class="h-7 text-xs"
                    >
                        <Trash2 class="h-3 w-3 mr-1" />
                        Delete selected ({{ dialogSelectedLinks.size }})
                    </Button>
                </div>
            </div>

            <!-- Content Area (scrollable) -->
            <div class="flex-1 overflow-hidden">
                <!-- Loading State -->
                <div v-if="dialogLinksLoading && dialogLinks.length === 0" class="flex items-center justify-center py-12">
                    <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
                    <span class="ml-2 text-sm text-muted-foreground">Loading links...</span>
                </div>

                <!-- Links List -->
                <ScrollArea v-else-if="dialogLinks.length > 0" class="h-full pr-4">
                    <div class="space-y-2">
                        <div
                            v-for="link in dialogLinks"
                            :key="link.id"
                            class="flex items-start gap-2 p-3 bg-background rounded border hover:bg-muted/50 transition-colors"
                        >
                            <Checkbox
                                :model-value="dialogSelectedLinks.has(link.id)"
                                @update:model-value="(checked) => toggleDialogLinkSelection(link.id, checked)"
                                :id="`dialog-link-${link.id}`"
                                class="mt-0.5 data-[state=checked]:bg-primary data-[state=checked]:border-primary flex-shrink-0"
                            />
                            <ExternalLink class="h-3 w-3 mt-0.5 text-muted-foreground flex-shrink-0" />
                            <div class="min-w-0 flex-1">
                                <a
                                    :href="link.link"
                                    target="_blank"
                                    class="text-foreground hover:text-primary hover:underline block break-words text-sm"
                                    @click.stop
                                >
                                    {{ link.link }}
                                </a>
                                <p v-if="link.crawled_at" class="text-xs text-muted-foreground mt-1">
                                    Crawled {{ formatDate(link.crawled_at) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <!-- Success animation for just-trained links -->
                                <TooltipProvider v-if="linkSuccessStates.has(link.id)">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div class="h-7 w-7 flex items-center justify-center">
                                                <CheckCircle2 class="h-4 w-4 text-green-600 animate-in zoom-in-50 duration-300" />
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Training completed!</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <!-- Loading state for pending or ingesting -->
                                <TooltipProvider v-else-if="link.status === 'pending' || link.status === 'ingesting'">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div class="h-7 w-7 flex items-center justify-center">
                                                <LoaderCircle class="h-3.5 w-3.5 animate-spin text-blue-600" />
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ link.status === 'pending' ? 'Training pending...' : 'Training in progress...' }}</p>
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
                                                class="h-7 w-7 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                            >
                                                <AlertCircle class="h-3.5 w-3.5" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Training failed. Click to retry</p>
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
                                                class="h-7 w-7 p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                            >
                                                <RefreshCw class="h-3.5 w-3.5" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Retrain this link</p>
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
                                                class="h-7 w-7 p-0 text-destructive hover:text-destructive/80 hover:bg-red-50"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Delete this link</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                    </div>
                </ScrollArea>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center py-12">
                    <Globe class="h-12 w-12 text-muted-foreground mb-3" />
                    <p class="text-sm text-muted-foreground">
                        {{ dialogSearchQuery.trim() ? 'No links match your search' : 'No links found' }}
                    </p>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div v-if="dialogLinks.length > 0 || dialogLinksPagination.total > 0" class="flex items-center justify-between pt-4 border-t mt-4 flex-shrink-0">
                <Button
                    variant="outline"
                    size="sm"
                    @click="loadPrevDialogLinksPage"
                    :disabled="!dialogLinksPagination.hasPrevPage || dialogLinksLoading"
                    class="h-9"
                >
                    <ChevronRight class="h-4 w-4 rotate-180 mr-1" />
                    Previous
                </Button>
                <span class="text-sm text-muted-foreground">
                    Page {{ dialogLinksPagination.currentPage }} of {{ dialogLinksPagination.lastPage }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    @click="loadNextDialogLinksPage"
                    :disabled="!dialogLinksPagination.hasNextPage || dialogLinksLoading"
                    class="h-9"
                >
                    Next
                    <ChevronRight class="h-4 w-4 ml-1" />
                </Button>
            </div>
        </DialogContent>
    </Dialog>
        </div>
    </div>

    <!-- Attach Website Dialog -->
    <AttachWebsiteDialog
        v-model:open="attachWebsiteDialogOpen"
        :agent-id="agent.id"
        @websiteAttached="handleWebsiteAttached"
    />
</template>
