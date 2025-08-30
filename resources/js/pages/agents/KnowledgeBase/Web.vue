<script setup lang="ts">
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useForm, router } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Globe, LoaderCircle, Plus, Trash2, ExternalLink, AlertCircle, CheckCircle, Play, Settings, ChevronDown, ChevronUp, ChevronRight, Database } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ScrollArea } from '@/components/ui/scroll-area';

import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import InfoBanner from '@/components/ui/InfoBanner.vue';
import { useEcho } from "@laravel/echo-vue";

interface Props {
    agent: any
}

interface WebSource {
    id: number;
    url: string;
    name?: string;
    status: 'pending' | 'crawling' | 'crawled' | 'ingesting' | 'ingested' | 'failed';
    created_at: string;
    error_message?: string;
    discovered_links?: DiscoveredLink[];
    length?: number;
    options?: {
        include_paths?: string;
        exclude_paths?: string;
        robots?: boolean;
        slow_crawling?: boolean;
    };
}
interface DiscoveredLink {
    link: string;
    text: string;
    found_on_url: string;
}



const props = defineProps<Props>();

// WebSocket event listener for crawling status updates
useEcho(
    `agents.${props.agent.id}.crawler`,
    "CrawlingWebsiteEvent",
    (e) => {
        handleCrawlingUpdate(e);
    },
)

// State
const webSources = ref<WebSource[]>([]);
const isLoadingSources = ref(false);
const isInitialLoad = ref(true);

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
const selectedLinks = ref<Map<number, Set<string>>>(new Map()); // sourceId -> Set of selected URLs
const linkSearchQueries = ref<Map<number, string>>(new Map()); // sourceId -> search query

// Full links cache - stores all links
const fullLinksCache = ref<Map<number, DiscoveredLink[]>>(new Map());

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

// Auto-refresh for crawling sources
const refreshInterval = ref<number | null>(null);

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
const canTrain = computed(() => selectedWebsiteCount.value > 0);
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
            return CheckCircle;
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

// API functions
const fetchWebsiteLinks = async (websiteId: number): Promise<DiscoveredLink[]> => {
    try {
        // Check if we already have all links cached
        const cachedLinks = fullLinksCache.value.get(websiteId);
        if (cachedLinks) {
            return cachedLinks;
        }

        // Fetch all links from API
        const response = await axios.get(route('agents.knowledge.websites.links', {
            agent: props.agent.id,
            website: websiteId
        }));

        // Handle different response formats and convert to DiscoveredLink[]
        let allLinksArray: string[] = [];

        if (Array.isArray(response.data)) {
            allLinksArray = response.data;
        } else if (response.data && Array.isArray(response.data.links)) {
            allLinksArray = response.data.links;
        } else if (response.data && Array.isArray(response.data.data)) {
            allLinksArray = response.data.data;
        } else {
            return [];
        }

        // Convert to DiscoveredLink format and cache
        const allDiscoveredLinks = allLinksArray.map((linkObj: any) => ({
            link: linkObj.link || linkObj.url || linkObj,
            text: linkObj.text || linkObj.title || (typeof linkObj === 'string' ? linkObj.split('/').pop() || linkObj : ''),
            found_on_url: linkObj.found_on_url || ''
        }));

        fullLinksCache.value.set(websiteId, allDiscoveredLinks);

        return allDiscoveredLinks;

    } catch (error) {
        console.error(`Failed to fetch links for website ${websiteId}:`, error);
        return [];
    }
};

const fetchWebSources = async (isBackgroundRefresh = false) => {
    if (!isBackgroundRefresh) {
        isLoadingSources.value = true;
    }

    try {
        const response = await axios.get(route('agents.knowledge.websites.list', { agent: props.agent.id }));

        // Transform the response data to match our WebSource interface
        webSources.value = response.data.map((website: any) => {
            const source = {
                id: website.id,
                url: website.url,
                name: getDomainFromUrl(website.url),
                status: website.status,
                created_at: website.created_at,
                length: website.length || 0,
                options: website.options || {}
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
        websiteForm.post(route('agents.knowledge.website.crawl', { agent: props.agent.id }), {
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

const deleteWebSource = async () => {
    if (!sourceToDelete.value) return;

    try {
        await axios.delete(route('agents.knowledge.websites.delete', {
            agent: props.agent.id,
            website: sourceToDelete.value.id
        }));

        // Remove from local state
        webSources.value = webSources.value.filter(source => source.id !== sourceToDelete.value!.id);

        // Also remove from selected websites if it was selected
        const newSelected = new Set(selectedWebsites.value);
        newSelected.delete(sourceToDelete.value.id);
        selectedWebsites.value = newSelected;

        toast.success('Website deleted successfully.');
    } catch (error: any) {
        console.error('Error deleting website:', error);
        toast.error(error.response?.data?.message || 'Failed to delete website.');
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
            webSources.value[sourceIndex].length = discoveredLinks.length; // Update the length property
            
            // Auto-select all links by default
            if (discoveredLinks.length > 0) {
                const allUrls = new Set(discoveredLinks.map(link => link.link));
                selectedLinks.value.set(websiteId, allUrls);
                
                // Initialize search query for this source
                if (!linkSearchQueries.value.has(websiteId)) {
                    linkSearchQueries.value.set(websiteId, '');
                }
            }
        }
    } catch (error) {
        console.error(`Failed to auto-load links for website ${websiteId}:`, error);
        // Don't show error toast for auto-loading, just log it
    }
};

const handleCrawlingUpdate = (event: any) => {
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
                length: 0 // We'll get the actual length when we fetch the links
            };
            toast.success(`Crawling completed for ${source.name}! Fetching discovered links...`);
            
            // Always fetch links when crawling finishes, regardless of event data
            if (website_id) {
                loadLinksForWebsite(website_id);
            }
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
                status: 'ingested'
            };
            toast.success(`Training completed for ${source.name}!`);
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

            // Auto-select all links by default
            if (discoveredLinks.length > 0) {
                const allUrls = new Set(discoveredLinks.map(link => link.link));
                selectedLinks.value.set(sourceId, allUrls);

                // Initialize search query for this source
                if (!linkSearchQueries.value.has(sourceId)) {
                    linkSearchQueries.value.set(sourceId, '');
                }

                console.log(`Auto-selected ${allUrls.size} links for website ${sourceId}`);
            } else {
                console.log(`No links found for website ${sourceId}`);
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
const toggleLinkSelection = (sourceId: number, link: string, checked: boolean | string) => {
    const isChecked = checked === true || checked === 'true';
    const sourceLinks = selectedLinks.value.get(sourceId) || new Set();

    if (isChecked) {
        sourceLinks.add(link);
    } else {
        sourceLinks.delete(link);
    }

    selectedLinks.value.set(sourceId, sourceLinks);
};

const toggleSelectAllLinks = (sourceId: number, links: DiscoveredLink[], checked: boolean | string | 'indeterminate') => {
    const isChecked = checked === true || checked === 'true';
    const sourceLinks = new Set<string>();

    if (isChecked) {
        // Get all cached links for selection, not just displayed ones
        const cachedLinks = fullLinksCache.value.get(sourceId);
        const allLinks = cachedLinks || links; // Fallback to provided links if not cached

        // Apply search filter to all links
        const filteredLinks = getFilteredLinks(sourceId, allLinks);
        filteredLinks.forEach(link => sourceLinks.add(link.link));
    }

    selectedLinks.value.set(sourceId, sourceLinks);
};

// Get filtered links based on search query
const getFilteredLinks = (sourceId: number, links: DiscoveredLink[]) => {
    const searchQuery = linkSearchQueries.value.get(sourceId) || '';
    if (!searchQuery.trim()) return links;

    const query = searchQuery.toLowerCase().trim();
    return links.filter(link =>
        link.link.toLowerCase().includes(query) ||
        link.text.toLowerCase().includes(query)
    );
};

// Get selection state for checkboxes
const getSelectionState = (sourceId: number, links: DiscoveredLink[]) => {
    const sourceLinks = selectedLinks.value.get(sourceId) || new Set();

    // Use cached links if available for more accurate selection state
    const cachedLinks = fullLinksCache.value.get(sourceId);
    const allLinks = cachedLinks || links;
    const filteredLinks = getFilteredLinks(sourceId, allLinks);

    if (filteredLinks.length === 0) return { allSelected: false, someSelected: false, selectedCount: 0 };

    const selectedFilteredLinks = filteredLinks.filter(link => sourceLinks.has(link.link));
    const allSelected = selectedFilteredLinks.length === filteredLinks.length;
    const someSelected = selectedFilteredLinks.length > 0 && selectedFilteredLinks.length < filteredLinks.length;

    return { allSelected, someSelected, selectedCount: sourceLinks.size };
};

// Update search query
const updateSearchQuery = (sourceId: number, query: string) => {
    linkSearchQueries.value.set(sourceId, query);
};





// Get total links count for display
const getTotalLinksCount = (sourceId: number): number => {
    // First check if we have cached links (this is the authoritative count)
    const cachedLinks = fullLinksCache.value.get(sourceId);
    if (cachedLinks) {
        return cachedLinks.length;
    }

    // Fallback to the source's length from API
    const source = webSources.value.find(s => s.id === sourceId);
    if (source?.length) {
        return source.length;
    }

    // Last fallback to currently displayed links
    return source?.discovered_links?.length || 0;
};

// Website selection functions
const toggleWebsiteSelection = (websiteId: number, checked: boolean | string) => {
    const isChecked = checked === true || checked === 'true';
    const newSelected = new Set(selectedWebsites.value);

    if (isChecked) {
        newSelected.add(websiteId);
    } else {
        newSelected.delete(websiteId);
    }

    selectedWebsites.value = newSelected;
};

const toggleSelectAllWebsites = (checked: boolean | string | 'indeterminate') => {
    const isChecked = checked === true || checked === 'true';
    const newSelected = new Set<number>();

    if (isChecked) {
        crawledSources.value.forEach(source => newSelected.add(source.id));
    }

    selectedWebsites.value = newSelected;
};

const trainSelectedWebsites = async () => {
    if (!canTrain.value || isTraining.value) return;

    isTraining.value = true;
    const selectedIds = Array.from(selectedWebsites.value);
    let successCount = 0;
    let failedWebsites: string[] = [];

    // Process each website individually
    for (const websiteId of selectedIds) {
        try {
            // Ensure links are loaded and selected for this website
            let selectedLinksForWebsite = selectedLinks.value.get(websiteId) || new Set();
            
            // If no links are selected, try to load and auto-select all links
            if (selectedLinksForWebsite.size === 0) {
                console.log(`No links selected for website ${websiteId}, loading all links...`);
                
                const websiteName = webSources.value.find(s => s.id === websiteId)?.name || 
                                  getDomainFromUrl(webSources.value.find(s => s.id === websiteId)?.url || '');
                toast.info(`Loading links for ${websiteName}...`);
                
                const discoveredLinks = await fetchWebsiteLinks(websiteId);
                if (discoveredLinks.length > 0) {
                    const allUrls = new Set(discoveredLinks.map(link => link.link));
                    selectedLinks.value.set(websiteId, allUrls);
                    selectedLinksForWebsite = allUrls;
                    console.log(`Auto-selected ${allUrls.size} links for website ${websiteId}`);
                } else {
                    console.warn(`No links found for website ${websiteId}`);
                }
            }

            const payload = {
                links: Array.from(selectedLinksForWebsite)
            };

            console.log(`Training website ${websiteId} with ${payload.links.length} links:`, payload.links);

            // Send individual request for each website
            await new Promise<void>((resolve, reject) => {
                router.post(route('agents.knowledge.website.train', { 
                    agent: props.agent.id, 
                    website: websiteId 
                }), payload, {
                    preserveScroll: true,
                    onSuccess: () => {
                        successCount++;
                        resolve();
                    },
                    onError: (errors) => {
                        const websiteName = webSources.value.find(s => s.id === websiteId)?.name || 
                                          getDomainFromUrl(webSources.value.find(s => s.id === websiteId)?.url || '');
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
        toast.success(`Training started for ${successCount} website${successCount !== 1 ? 's' : ''}!`);
        
        // Clear selection after successful training
        selectedWebsites.value = new Set();
        
        // Clear selected links for successfully trained websites
        selectedIds.forEach(websiteId => {
            selectedLinks.value.delete(websiteId);
        });
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





// Lifecycle
onMounted(() => {
    syncFormWithSettings();
    fetchWebSources();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Knowledge Base - Web">
        <div class="space-y-6">
        <!-- Smart Monitoring Info Banner -->
        <InfoBanner
            title="Smart Monitoring"
            message="Your websites will be periodically checked for changes and automatically re-crawled when necessary to keep your agent's knowledge up-to-date."
            storage-key="smart-monitoring-banner-dismissed"
            variant="info"
        />

        <!-- Add Website Section -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Globe class="h-5 w-5" />
                            Crawl links
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
                                        class="text-sm"
                                    />
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
                                        v-model="crawlSettings.exclude_paths"
                                    />
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

                    <!-- Error message -->
                    <div v-if="websiteForm.errors.url" class="text-sm text-red-600">
                        {{ websiteForm.errors.url }}
                    </div>

                    <!-- General error message -->
                    <div v-if="Object.keys(websiteForm.errors).length > 0 && !websiteForm.errors.url" class="text-sm text-red-600">
                        Please check your input and try again.
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Crawling Websites -->
        <Card v-if="crawlingSources.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <LoaderCircle class="h-5 w-5 animate-spin text-blue-600" />
                    Crawling Websites
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
                                        <Badge variant="outline" class="text-xs flex-shrink-0">
                                            Website
                                        </Badge>
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
                                                :disabled="source.status === 'crawling'"
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
                    <CheckCircle class="h-5 w-5 text-purple-600" />
                    Crawled Websites
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
                            {{ selectedWebsiteCount }} selected
                        </span>
                        <Button
                            @click="trainSelectedWebsites"
                            :disabled="!canTrain || isTraining"
                            size="sm"
                        >
                            <LoaderCircle v-if="isTraining" class="h-4 w-4 mr-2 animate-spin" />
                            <Database v-else class="h-4 w-4 mr-2" />
                            Train
                        </Button>
                    </div>
                </div>
                <div class="space-y-2">
                    <template v-for="source in crawledSources" :key="source.id">
                        <Collapsible v-if="source.length && source.length > 0" v-slot="{ open }" class="border rounded-lg" @update:open="(isOpen) => handleCollapsibleToggle(source.id, isOpen)">
                            <div class="flex items-center justify-between p-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
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
                                                            {{ source.length }} links
                                                        </Badge>
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
                                    <!-- Header with search and select all -->
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="text-sm font-medium text-muted-foreground">
                                            Discovered Links ({{ getTotalLinksCount(source.id) }})
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ source.discovered_links ? getSelectionState(source.id, source.discovered_links).selectedCount : 0 }} selected
                                        </div>
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
                                            Select All ({{ getTotalLinksCount(source.id) }})
                                        </Label>
                                    </div>

                                    <!-- Links list with infinite scroll -->
                                    <ScrollArea class="h-72 w-full rounded-md border">
                                        <div class="p-4">
                                            <!-- Initial loading state -->
                                            <div v-if="!source.discovered_links" class="flex items-center justify-center py-8">
                                                <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
                                                <span class="ml-2 text-sm text-muted-foreground">Loading links...</span>
                                            </div>

                                            <!-- Links loaded -->
                                            <div v-else class="space-y-2">
                                                <div
                                                    v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                    :key="link.link"
                                                    class="flex items-start gap-2 p-2 bg-background rounded border text-xs hover:bg-muted/50 transition-colors"
                                                >
                                                    <Checkbox
                                                        :model-value="(selectedLinks.get(source.id) || new Set()).has(link.link)"
                                                        @update:model-value="(checked) => toggleLinkSelection(source.id, link.link, checked)"
                                                        :id="`link-${source.id}-${link.link}`"
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
                                                        <div v-if="link.text" class="text-xs text-muted-foreground mt-1">
                                                            {{ link.text }}
                                                        </div>
                                                    </div>
                                                </div>



                                                <!-- No results message -->
                                                <div v-if="getFilteredLinks(source.id, source.discovered_links).length === 0" class="text-center py-4 text-muted-foreground text-xs">
                                                    No links match your search
                                                </div>
                                            </div>
                                        </div>
                                    </ScrollArea>
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
                                            <Badge variant="outline" class="text-xs flex-shrink-0">
                                                Website
                                            </Badge>
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
                    <Database class="h-5 w-5 text-blue-600" />
                    Training Websites
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
                                        <Badge variant="outline" class="text-xs flex-shrink-0">
                                            Website
                                        </Badge>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div class="flex items-center">
                                                        <component
                                                            :is="getStatusIcon(source.status)"
                                                            :class="`h-4 w-4 flex-shrink-0 ${getStatusColor(source.status)} animate-spin`"
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
                                                :disabled="true"
                                                @click="confirmDeleteSource(source)"
                                            >
                                                <span class="sr-only">Remove source</span>
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Cannot delete while training</p>
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
                <CardTitle class="flex items-center gap-2">
                    <Database class="h-5 w-5" />
                    Trained
                </CardTitle>
                <CardDescription>
                    Websites trained and available to your agent. {{ completedSources.length }} website{{ completedSources.length !== 1 ? 's' : '' }} in total.
                </CardDescription>
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
                                                            <Badge variant="outline" class="text-xs flex-shrink-0">
                                                                Website
                                                            </Badge>
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
                                                        <!-- Header with search and select all -->
                                                        <div class="flex items-center justify-between mb-3">
                                                            <div class="text-sm font-medium text-muted-foreground">
                                                                Discovered Links ({{ getFilteredLinks(source.id, source.discovered_links).length }}{{ getFilteredLinks(source.id, source.discovered_links).length !== source.discovered_links.length ? ` of ${source.discovered_links.length}` : '' }})
                                                            </div>
                                                            <div class="text-xs text-muted-foreground">
                                                                {{ getSelectionState(source.id, source.discovered_links).selectedCount }} selected
                                                            </div>
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
                                                                Select All {{ getFilteredLinks(source.id, source.discovered_links).length > 0 ? `(${getFilteredLinks(source.id, source.discovered_links).length})` : '' }}
                                                            </Label>
                                                        </div>

                                                        <!-- Links list with infinite scroll -->
                                                        <ScrollArea class="h-72 w-full rounded-md border">
                                                            <div class="p-4">
                                                                <div class="space-y-2">
                                                                    <div
                                                                        v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                                        :key="link.link"
                                                                        class="flex items-start gap-2 p-2 bg-background rounded border text-xs hover:bg-muted/50 transition-colors"
                                                                    >
                                                                        <Checkbox
                                                                            :model-value="(selectedLinks.get(source.id) || new Set()).has(link.link)"
                                                                            @update:model-value="(checked) => toggleLinkSelection(source.id, link.link, checked)"
                                                                            :id="`link-${source.id}-${link.link}`"
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
                                                                            <div v-if="link.text" class="text-xs text-muted-foreground mt-1">
                                                                                {{ link.text }}
                                                                            </div>
                                                                        </div>
                                                                    </div>



                                                                    <!-- No results message -->
                                                                    <div v-if="getFilteredLinks(source.id, source.discovered_links || []).length === 0" class="text-center py-4 text-muted-foreground text-xs">
                                                                        No links match your search
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </ScrollArea>
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
                                                        <Badge variant="outline" class="text-xs flex-shrink-0">
                                                            Website
                                                        </Badge>
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
                                                    <!-- Header with search and select all -->
                                                    <div class="flex items-center justify-between mb-3">
                                                        <div class="text-sm font-medium text-muted-foreground">
                                                            Discovered Links ({{ getTotalLinksCount(source.id) }})
                                                        </div>
                                                        <div class="text-xs text-muted-foreground">
                                                            {{ getSelectionState(source.id, source.discovered_links).selectedCount }} selected
                                                        </div>
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
                                                            Select All ({{ getTotalLinksCount(source.id) }})
                                                        </Label>
                                                    </div>

                                                    <!-- Links list -->
                                                    <ScrollArea class="h-72 w-full rounded-md border">
                                                        <div class="p-4">
                                                            <div class="space-y-2">
                                                                <div
                                                                    v-for="link in getFilteredLinks(source.id, source.discovered_links)"
                                                                    :key="link.link"
                                                                    class="flex items-start gap-2 p-2 bg-background rounded border text-xs hover:bg-muted/50 transition-colors"
                                                                >
                                                                    <Checkbox
                                                                        :model-value="(selectedLinks.get(source.id) || new Set()).has(link.link)"
                                                                        @update:model-value="(checked) => toggleLinkSelection(source.id, link.link, checked)"
                                                                        :id="`link-${source.id}-${link.link}`"
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
                                                                        <div v-if="link.text" class="text-xs text-muted-foreground mt-1">
                                                                            {{ link.text }}
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- No results message -->
                                                                <div v-if="getFilteredLinks(source.id, source.discovered_links).length === 0" class="text-center py-4 text-muted-foreground text-xs">
                                                                    No links match your search
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </ScrollArea>
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
        </div>
    </AgentLayout>

    <!-- Delete Confirmation Dialog -->
    <DeleteConfirmationDialog
        v-model:open="deleteDialogOpen"
        :title="`Delete ${sourceToDelete?.name || getDomainFromUrl(sourceToDelete?.url || '')}?`"
        description="This will permanently remove this web source from your agent's knowledge base. All crawled pages will be deleted. This action cannot be undone."
        confirm-text="Yes, delete source"
        cancel-text="Cancel"
        @confirm="deleteWebSource"
    />
</template>
