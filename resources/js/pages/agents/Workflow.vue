<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card, CardHeader } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Bot, Code, Database, Filter, Globe, MessageSquare, Play, Plus, RotateCcw, Save, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

// Vue Flow imports
import { Background } from '@vue-flow/background';
import { ControlButton, Controls } from '@vue-flow/controls';
import type { Connection, Edge, Node } from '@vue-flow/core';
import { Handle, Position, VueFlow, useVueFlow } from '@vue-flow/core';

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
});

// Initial nodes and edges
const initialNodes: Node[] = [
    {
        id: '1',
        type: 'workflowNode',
        position: { x: 250, y: 50 },
        data: {
            name: 'Start',
            icon: Bot,
            color: 'green',
            nodeType: 'input',
        },
    },
    {
        id: '2',
        type: 'workflowNode',
        position: { x: 100, y: 200 },
        data: {
            name: 'Process',
            icon: Database,
            color: 'blue',
            nodeType: 'process',
        },
    },
    {
        id: '3',
        type: 'workflowNode',
        position: { x: 400, y: 200 },
        data: {
            name: 'Decision',
            icon: Filter,
            color: 'orange',
            nodeType: 'decision',
        },
    },
];

const initialEdges: Edge[] = [
    { id: 'e1-2', source: '1', target: '2', animated: true },
    { id: 'e1-3', source: '2', target: '3' },
];

// Workflow state
const nodes = ref<Node[]>(initialNodes);
const edges = ref<Edge[]>(initialEdges);

// Vue Flow composable with event hooks
const { getEdges, onInit, onNodeDragStop, onConnect, addEdges, addNodes, removeNodes, removeEdges, setViewport, toObject } = useVueFlow();

const showNodeLibrary = ref(false);
const selectedNode = ref<string | null>(null);
const dark = ref(false);

// Node types available in the library
const nodeTypes = [
    {
        id: 'llm',
        name: 'LLM Query',
        description: 'Send a query to the language model',
        icon: Bot,
        color: 'blue',
        category: 'AI',
    },
    {
        id: 'input',
        name: 'User Input',
        description: 'Accept input from the user',
        icon: MessageSquare,
        color: 'green',
        category: 'Input',
    },
    {
        id: 'database',
        name: 'Database Query',
        description: 'Query your knowledge base',
        icon: Database,
        color: 'purple',
        category: 'Data',
    },
    {
        id: 'api',
        name: 'API Call',
        description: 'Make an external API request',
        icon: Globe,
        color: 'orange',
        category: 'External',
    },
    {
        id: 'code',
        name: 'Code Execution',
        description: 'Execute custom code logic',
        icon: Code,
        color: 'gray',
        category: 'Logic',
    },
    {
        id: 'filter',
        name: 'Filter',
        description: 'Filter or transform data',
        icon: Filter,
        color: 'yellow',
        category: 'Logic',
    },
];

const nodeCategories = computed(() => {
    const categories = [...new Set(nodeTypes.map((node) => node.category))];
    return categories.map((category) => ({
        name: category,
        nodes: nodeTypes.filter((node) => node.category === category),
    }));
});

// Add node to canvas
const addNode = (nodeType: any) => {
    const newNode: Node = {
        id: Date.now().toString(),
        type: 'workflowNode',
        position: {
            x: Math.random() * 400 + 100,
            y: Math.random() * 300 + 100,
        },
        data: {
            name: nodeType.name,
            icon: nodeType.icon,
            color: nodeType.color,
            nodeType: nodeType.id,
        },
    };

    addNodes([newNode]);
    showNodeLibrary.value = false;
};

// Vue Flow event hooks
/**
 * onInit is called when the VueFlow viewport is initialized
 */
onInit((vueFlowInstance) => {
    vueFlowInstance.fitView();
});

/**
 * onNodeDragStop is called when a node is done being dragged
 */
onNodeDragStop(({ event, nodes, node }) => {
    console.log('Node Drag Stop', { event, nodes, node });
});

/**
 * onConnect is called when a new connection is created
 */
onConnect((connection: Connection) => {
    console.log('Creating connection:', connection);

    // Validate the connection
    if (!connection.source || !connection.target) {
        console.warn('Invalid connection:', connection);
        return;
    }

    // Prevent connecting to itself
    if (connection.source === connection.target) {
        console.warn('Cannot connect node to itself');
        return;
    }

    // Check if this exact connection already exists
    const existingConnection = edges.value.find((edge) => edge.source === connection.source && edge.target === connection.target);

    if (existingConnection) {
        console.warn('Connection already exists between these nodes');
        return;
    }

    // Check for existing connections - enforce 1-to-1 rule
    const existingOutgoing = edges.value.find((edge) => edge.source === connection.source);
    const existingIncoming = edges.value.find((edge) => edge.target === connection.target);

    // Remove existing outgoing connection from source node
    if (existingOutgoing) {
        removeEdges([existingOutgoing.id]);
    }

    // Remove existing incoming connection to target node
    if (existingIncoming) {
        removeEdges([existingIncoming.id]);
    }

    // Add the new edge with a unique ID
    const newEdge: Edge = {
        id: `e${connection.source}-${connection.target}-${Date.now()}`,
        source: connection.source,
        target: connection.target,
        sourceHandle: connection.sourceHandle,
        targetHandle: connection.targetHandle,
        animated: Math.random() > 0.5,
    };

    addEdges([newEdge]);
});

// Delete node
const deleteNode = (nodeId: string) => {
    removeNodes([nodeId]);
    // Edges connected to this node will be automatically removed
};

// Get node color classes
const getNodeColorClasses = (color: string) => {
    const colorMap: Record<string, string> = {
        blue: 'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400',
        green: 'bg-green-50 text-green-600 dark:bg-green-950 dark:text-green-400',
        purple: 'bg-purple-50 text-purple-600 dark:bg-purple-950 dark:text-purple-400',
        orange: 'bg-orange-50 text-orange-600 dark:bg-orange-950 dark:text-orange-400',
        gray: 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        yellow: 'bg-yellow-50 text-yellow-600 dark:bg-yellow-950 dark:text-yellow-400',
    };
    return colorMap[color] || colorMap['gray'];
};

// Vue Flow utility functions
/**
 * To update node positions randomly
 */
function updatePos() {
    nodes.value = nodes.value.map((node) => {
        return {
            ...node,
            position: {
                x: Math.random() * 400,
                y: Math.random() * 400,
            },
        };
    });
}

/**
 * toObject transforms your current graph data to an easily persist-able object
 */
function logToObject() {
    console.log(toObject());
}

/**
 * Resets the current viewport transformation (zoom & pan)
 */
function resetTransform() {
    setViewport({ x: 0, y: 0, zoom: 1 });
}

function toggleDarkMode() {
    dark.value = !dark.value;
    // Toggle the dark class on the document root to affect the entire page
    if (dark.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

// Save workflow
const saveWorkflow = () => {
    const workflow = toObject();
    console.log('Saving workflow:', workflow);
    // TODO: Implement save functionality
};

// Run workflow
const runWorkflow = () => {
    console.log('Running workflow...');
    // TODO: Implement workflow execution
};

// Vue Flow connection handler is set up via onConnect composable above

onMounted(() => {
    // Initial nodes are already set, no need to add more
    console.log('Workflow mounted with initial nodes');

    // Initialize dark mode based on current theme
    dark.value = document.documentElement.classList.contains('dark');
});

// Allow only one incoming edge per target node
function isValidConnection(connection) {
    const { target } = connection;
    if (!target) return false;

    // check if target already has an incoming edge
    const edges = getEdges.value;
    const alreadyConnected = edges.some((e) => e.target === target);

    return !alreadyConnected;
}
</script>

<template>
    <AgentLayout :agent="agent" title="Workflow">
        <Head title="Workflow" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Workflow Designer</h1>
                    <p class="text-muted-foreground">Create and manage automated workflows for your agent</p>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="showNodeLibrary = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Node
                    </Button>
                    <Button variant="outline" @click="saveWorkflow">
                        <Save class="mr-2 h-4 w-4" />
                        Save
                    </Button>
                    <Button @click="runWorkflow">
                        <Play class="mr-2 h-4 w-4" />
                        Run
                    </Button>
                </div>
            </div>

            <!-- Workflow Canvas -->
            <Card class="relative h-[600px] overflow-hidden">
                <VueFlow
                    v-model:nodes="nodes"
                    v-model:edges="edges"
                    is-valid-connection="isValidConnection"
                    :class="{ dark }"
                    class="workflow-canvas"
                    :snap-to-grid="true"
                    :snap-grid="[20, 20]"
                    :default-viewport="{ zoom: 1.5 }"
                    :min-zoom="0.2"
                    :max-zoom="4"
                    :connection-mode="'loose'"
                    :connect-on-click="false"
                    :nodes-connectable="true"
                    :edges-updatable="true"
                    :nodes-draggable="true"
                    :connection-line-style="{ stroke: '#b1b1b7', strokeWidth: 2 }"
                >
                    <!-- Custom Node Template -->
                    <template #node-workflowNode="{ data, id }">
                        <div class="workflow-node group relative">
                            <div class="flex flex-col items-center">
                                <!-- Main node box with handles positioned on it -->
                                <div
                                    class="relative flex h-12 w-36 cursor-move items-center justify-center rounded-md border-2 bg-white transition-all duration-200 select-none"
                                    :class="[
                                        {
                                            'border-blue-500': data.color === 'blue',
                                            'border-red-500': data.color === 'red',
                                            'border-orange-500': data.color === 'orange',
                                            'border-green-500': data.color === 'green',
                                            'border-purple-500': data.color === 'purple',
                                            'border-gray-400': !data.color || !['blue', 'red', 'orange', 'green', 'purple'].includes(data.color),
                                        },
                                        selectedNode === id ? 'shadow-lg' : 'shadow-sm hover:shadow-md',
                                    ]"
                                    @click="selectedNode = id"
                                >
                                    <!-- Vue Flow Handles - Now positioned relative to the box itself -->
                                    <Handle
                                        type="target"
                                        :position="Position.Left"
                                        class="!absolute !h-3 !w-3 !rounded-full !border-0 !bg-gray-800"
                                        style="left: -6px; top: 50%; transform: translateY(-50%)"
                                    />
                                    <Handle
                                        type="source"
                                        :position="Position.Right"
                                        class="!absolute !h-3 !w-3 !rounded-full !border-0 !bg-gray-800"
                                        style="right: -6px; top: 50%; transform: translateY(-50%)"
                                    />
                                    <!-- Node content - clean text only -->
                                    <div class="px-3 text-center">
                                        <h3 class="text-sm leading-tight font-medium text-gray-900">{{ data.name }}</h3>
                                    </div>
                                </div>

                                <!-- Delete button below the box, centered -->
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="z-10 mt-2 h-6 w-6 rounded-full bg-red-500 p-0 text-white shadow-sm hover:bg-red-600"
                                    @click.stop="deleteNode(id)"
                                    title="Delete node"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    </template>

                    <!-- Background with dots -->
                    <Background pattern-color="#aaa" :gap="16" />

                    <!-- Enhanced Controls -->
                    <Controls position="top-left">
                        <ControlButton title="Reset Transform" @click="resetTransform">
                            <RotateCcw class="h-4 w-4" />
                        </ControlButton>
                    </Controls>
                </VueFlow>
            </Card>
        </div>

        <!-- Node Library Dialog -->
        <Dialog v-model:open="showNodeLibrary">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add Node</DialogTitle>
                    <DialogDescription> Choose a node type to add to your workflow </DialogDescription>
                </DialogHeader>

                <ScrollArea class="h-96">
                    <div class="space-y-4">
                        <div v-for="category in nodeCategories" :key="category.name">
                            <h3 class="text-muted-foreground mb-2 text-sm font-semibold">
                                {{ category.name }}
                            </h3>
                            <div class="grid grid-cols-2 gap-2">
                                <Card
                                    v-for="node in category.nodes"
                                    :key="node.id"
                                    class="hover:bg-muted/50 cursor-pointer transition-colors"
                                    @click="addNode(node)"
                                >
                                    <CardHeader class="p-3">
                                        <div class="flex items-start gap-3">
                                            <div class="rounded-md p-2" :class="getNodeColorClasses(node.color)">
                                                <component :is="node.icon" class="h-4 w-4" />
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium">{{ node.name }}</h4>
                                                <p class="text-muted-foreground mt-1 text-xs">
                                                    {{ node.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </CardHeader>
                                </Card>
                            </div>
                            <Separator v-if="category !== nodeCategories[nodeCategories.length - 1]" class="mt-4" />
                        </div>
                    </div>
                </ScrollArea>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>

<style>
/* Import Vue Flow styles */
@import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.46.0/dist/style.css';
@import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.46.0/dist/theme-default.css';
@import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
@import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
@import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';
</style>
