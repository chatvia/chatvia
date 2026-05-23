<template>
    <AgentLayout :agent="agent" title="Widget Configuration" hide-header no-padding full-height>
        <div class="flex h-full">
            <!-- Left Column - Navigation Menu -->
            <div class="bg-muted/30 flex h-full w-[180px] flex-shrink-0 flex-col border-r">
                <nav class="flex flex-col gap-0.5 p-2">
                    <button
                        v-for="item in menuItems"
                        :key="item.value"
                        @click="activeTab = item.value"
                        class="flex h-9 w-full cursor-pointer items-center gap-2.5 rounded-md px-3 text-sm transition-colors"
                        :class="[
                            activeTab === item.value
                                ? 'bg-background text-foreground font-medium shadow-sm'
                                : 'text-muted-foreground hover:bg-background/50 hover:text-foreground',
                        ]"
                    >
                        <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                        <span>{{ item.label }}</span>
                    </button>
                </nav>
            </div>

            <!-- Middle Column - Configuration Content -->
            <div class="flex h-full flex-1 flex-col overflow-hidden border-r">
                <!-- Permission Warning Banner -->
                <Alert v-if="!canEdit" variant="destructive" class="rounded-none border-x-0 border-t-0">
                    <Shield class="h-4 w-4" />
                    <AlertTitle>View Only</AlertTitle>
                    <AlertDescription>
                        You don't have permission to edit widget settings. Contact your team owner to request access.
                    </AlertDescription>
                </Alert>

                <div class="flex-1 overflow-y-auto" :class="{ 'pointer-events-none opacity-60 select-none': !canEdit }">
                    <!-- Text Panel -->
                    <div v-if="activeTab === 'text'" class="space-y-6 p-6">
                        <form @submit.prevent="updateWidgetSettings" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="chat_window_title">Chat Title</Label>
                                <Input id="chat_window_title" v-model="appearanceForm.chat_window_title" placeholder="Chat with us" />
                                <p v-if="appearanceForm.errors.chat_window_title" class="text-destructive text-sm">
                                    {{ appearanceForm.errors.chat_window_title }}
                                </p>
                                <p class="text-muted-foreground text-xs">This title appears at the top of the chat window</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="welcome_message">Welcome Message</Label>
                                <Input id="welcome_message" v-model="appearanceForm.welcome_message" placeholder="Hello! How can I help you today?" />
                                <p v-if="appearanceForm.errors.welcome_message" class="text-destructive text-sm">
                                    {{ appearanceForm.errors.welcome_message }}
                                </p>
                                <p class="text-muted-foreground text-xs">First message users see when opening the widget</p>
                            </div>

                            <div class="space-y-2">
                                <Label>Chat Bubbles</Label>
                                <p class="text-muted-foreground mb-2 text-xs">Quick message prompts to help users start conversations</p>

                                <TagsInput v-model="widgetForm.bubbles" class="w-full">
                                    <TagsInputItem v-for="(bubble, index) in widgetForm.bubbles" :key="index" :value="bubble">
                                        <TagsInputItemText />
                                        <TagsInputItemDelete />
                                    </TagsInputItem>
                                    <TagsInputInput placeholder="e.g., How can I help you?" />
                                </TagsInput>

                                <p v-if="widgetForm.errors.bubbles" class="text-destructive text-sm">
                                    {{ widgetForm.errors.bubbles }}
                                </p>
                            </div>

                            <!-- Save Button -->
                            <div class="border-t pt-4">
                                <Button type="submit" :disabled="!canEdit || isSubmitting || !hasUnsavedChanges" size="sm">
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                                </Button>
                                <p v-if="showSuccessMessage" class="mt-2 text-sm text-green-600">Settings saved successfully</p>
                            </div>
                        </form>
                    </div>

                    <!-- Persona Panel -->
                    <div v-if="activeTab === 'persona'" class="p-6">
                        <AgentPersonaEditor :agent-id="agent.external_id" :initial-instructions="agent.system_instructions" @saved="refreshPreview" />
                    </div>

                    <!-- Starters Panel -->
                    <div v-if="activeTab === 'starters'" class="space-y-4 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium">Conversation Starters</h3>
                                <p class="text-muted-foreground mt-1 text-xs">Suggest questions to help users start conversations</p>
                            </div>
                            <Button type="button" variant="outline" size="sm" @click="showAddStarterDialog = true" :disabled="!canEdit">
                                <PlusCircle class="mr-2 h-4 w-4" />
                                Add Starter
                            </Button>
                        </div>

                        <div class="rounded-md border">
                            <div v-if="starters.length === 0" class="text-muted-foreground p-4 text-center text-sm">No starter messages yet</div>
                            <div v-for="starter in starters" :key="starter.id" class="flex items-center justify-between border-b p-3 last:border-0">
                                <div class="flex items-center gap-3">
                                    <MessageSquare class="text-muted-foreground h-4 w-4" />
                                    <div class="text-sm">{{ starter.text }}</div>
                                </div>
                                <Button
                                    type="button"
                                    class="text-destructive hover:text-destructive/80 h-8 w-8 p-0"
                                    size="sm"
                                    variant="ghost"
                                    :disabled="!canEdit"
                                    @click="removeStarter(starter.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <Alert>
                            <Info class="h-4 w-4" />
                            <AlertDescription>
                                Good starter messages are specific questions that showcase your agent's capabilities
                            </AlertDescription>
                        </Alert>
                    </div>

                    <!-- Models Panel -->
                    <div v-if="activeTab === 'models'" class="p-6">
                        <AgentModelSelector
                            :agent-id="agent.external_id"
                            description="Select which AI model to use for your widget"
                            @saved="refreshPreview"
                        />
                    </div>

                    <!-- Colors Panel -->
                    <div v-if="activeTab === 'colors'" class="space-y-2 p-6">
                        <Label>Widget Color</Label>
                        <p class="text-muted-foreground mb-3 text-xs">Choose the primary color for your widget</p>

                        <div class="relative inline-block cursor-pointer" @click="showColorPicker = true">
                            <div
                                class="border-border h-20 w-20 rounded-lg border-2 shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-md"
                                :style="{ backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})` }"
                            >
                                <div class="flex h-full items-center justify-center">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                        <div
                                            class="h-6 w-6 rounded-full"
                                            :style="{ backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="appearanceForm.errors.color_theme" class="text-destructive text-sm">
                            {{ appearanceForm.errors.color_theme }}
                        </p>
                    </div>

                    <!-- Behavior Panel -->
                    <div v-if="activeTab === 'behavior'" class="space-y-6 p-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="position">Position</Label>
                                <Select v-model="widgetForm.position" @update:model-value="saveBehaviorSettings">
                                    <SelectTrigger class="w-full">
                                        <SelectValue placeholder="Select position" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="bottom-right">Bottom Right</SelectItem>
                                        <SelectItem value="bottom-left">Bottom Left</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-muted-foreground text-xs">Where the widget appears on the page</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="window_size">Window Size</Label>
                                <Select v-model="widgetForm.window_size" @update:model-value="saveBehaviorSettings">
                                    <SelectTrigger class="w-full">
                                        <SelectValue placeholder="Select size" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="small">Small</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="large">Large</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-muted-foreground text-xs">Chat window dimensions</p>
                            </div>
                        </div>

                        <Separator />

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="space-y-0.5">
                                    <Label for="show_on_load">Auto-open Widget</Label>
                                    <p class="text-muted-foreground text-xs">Automatically show widget when page loads</p>
                                </div>
                                <Switch
                                    id="show_on_load"
                                    :model-value="widgetForm.show_on_load"
                                    @update:model-value="
                                        (value: any) => {
                                            widgetForm.show_on_load = value;
                                            saveBehaviorSettings();
                                        }
                                    "
                                />
                            </div>

                            <div v-if="widgetForm.show_on_load" class="space-y-2">
                                <Label for="show_on_load_delay">Display Delay (ms)</Label>
                                <Input
                                    id="show_on_load_delay"
                                    v-model.number="widgetForm.show_on_load_delay"
                                    type="number"
                                    min="0"
                                    step="100"
                                    placeholder="500"
                                    @blur="saveBehaviorSettings"
                                />
                                <p class="text-muted-foreground text-xs">Time to wait before showing the widget</p>
                            </div>
                        </div>

                        <Alert>
                            <Info class="h-4 w-4" />
                            <AlertDescription> Position, window size, and chat bubbles are visible in preview mode </AlertDescription>
                        </Alert>
                    </div>

                    <!-- Embed Panel -->
                    <div v-if="activeTab === 'embed'" class="space-y-6 p-6">
                        <!-- Allowed Websites Section -->
                        <form @submit.prevent="saveSecuritySettings">
                            <Alert v-if="!widgetForm.sites || widgetForm.sites.length === 0" variant="destructive" class="mb-4">
                                <Shield class="h-4 w-4" />
                                <AlertTitle>Important</AlertTitle>
                                <AlertDescription> You must specify allowed websites below, otherwise your widget will not work. </AlertDescription>
                            </Alert>

                            <div class="space-y-2">
                                <Label>Allowed Websites</Label>
                                <p class="text-muted-foreground mb-2 text-xs">
                                    Enter domains that can use this widget (without https://). Press Enter to add.
                                </p>

                                <TagsInput v-model="widgetForm.sites" class="w-full">
                                    <TagsInputItem v-for="(site, index) in widgetForm.sites" :key="index" :value="site">
                                        <TagsInputItemText />
                                        <TagsInputItemDelete />
                                    </TagsInputItem>
                                    <TagsInputInput placeholder="example.com" />
                                </TagsInput>

                                <p v-if="widgetForm.errors.sites" class="text-destructive text-sm">
                                    {{ widgetForm.errors.sites }}
                                </p>

                                <p class="text-muted-foreground text-xs">Examples: example.com, www.example.com, *.example.com</p>
                            </div>

                            <div class="pt-3">
                                <Button type="submit" :disabled="!canEdit || !hasSecurityChanges" size="sm">
                                    <Save class="mr-2 h-4 w-4" />
                                    Save Domains
                                </Button>
                            </div>
                        </form>

                        <!-- Language Selection Section -->
                        <div class="space-y-2 border-t pt-4">
                            <Label for="widget-language">Widget Language</Label>
                            <Select v-model="selectedLanguage">
                                <SelectTrigger id="widget-language" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, code) in availableLanguages" :key="code" :value="code">
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-muted-foreground text-xs">
                                Select the language for the widget interface. Auto-detect will use the visitor's browser language.
                            </p>
                        </div>

                        <!-- Embed Code Section -->
                        <div class="space-y-4 border-t pt-4">
                            <div>
                                <h3 class="mb-1 text-sm font-medium">Installation Code</h3>
                                <p class="text-muted-foreground text-xs">
                                    Copy and paste this code into your website's
                                    <code class="bg-muted rounded px-1 py-0.5 text-xs">&lt;head&gt;</code> section
                                </p>
                            </div>

                            <div class="bg-muted space-y-3 rounded-lg p-4">
                                <div class="flex items-center justify-end gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="copyEmbedSnippet" class="gap-2">
                                        <Copy class="h-4 w-4" />
                                        Copy Code
                                    </Button>
                                    <Button type="button" variant="outline" size="sm" @click="openPreview" class="gap-2">
                                        <Eye class="h-4 w-4" />
                                        Full Preview
                                    </Button>
                                </div>
                                <div class="bg-background rounded border p-3">
                                    <pre class="text-foreground overflow-x-auto text-xs"><code v-text="embedSnippet"></code></pre>
                                </div>
                            </div>

                            <!-- JavaScript API Button -->
                            <Button type="button" variant="outline" class="w-full justify-start gap-2" @click="showApiDocsDialog = true">
                                <Terminal class="h-4 w-4" />
                                JavaScript API Documentation
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Live Preview -->
            <div class="bg-background flex h-full w-[420px] flex-shrink-0 flex-col">
                <!-- Preview Header -->
                <div class="flex flex-shrink-0 items-center justify-between border-b px-4 py-3">
                    <span class="text-muted-foreground text-sm font-medium">Live Preview</span>
                    <div class="flex items-center gap-1">
                        <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5" @click="refreshPreview">
                            <RotateCcw class="h-3.5 w-3.5" />
                            <span class="text-xs">Refresh</span>
                        </Button>
                        <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5" @click="openFullPreview">
                            <Eye class="h-3.5 w-3.5" />
                            <span class="text-xs">Preview</span>
                        </Button>
                    </div>
                </div>

                <!-- Preview Frame -->
                <div class="flex-1 overflow-hidden">
                    <iframe
                        :key="previewKey"
                        :src="previewUrl"
                        class="h-full w-full border-0"
                        title="Widget Live Preview"
                    />
                </div>
            </div>
        </div>

        <!-- Widget Preview Modal -->
        <PreviewModal v-model="showPreviewModal" preview-type="widget" :preview-url="fullPreviewUrl" iframe-title="Widget Preview" />

        <!-- Color Picker Dialog -->
        <Dialog v-model:open="showColorPicker">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Palette class="h-5 w-5" />
                        Choose Widget Color
                    </DialogTitle>
                    <DialogDescription> Select a color and shade for your widget's primary theme </DialogDescription>
                </DialogHeader>

                <div class="space-y-6">
                    <!-- Color Grid -->
                    <div class="grid grid-cols-8 gap-2">
                        <div
                            v-for="theme in [
                                'black',
                                'red',
                                'orange',
                                'amber',
                                'yellow',
                                'lime',
                                'green',
                                'emerald',
                                'teal',
                                'cyan',
                                'sky',
                                'blue',
                                'indigo',
                                'violet',
                                'purple',
                                'fuchsia',
                                'pink',
                                'rose',
                                'slate',
                                'gray',
                                'zinc',
                                'neutral',
                                'stone',
                            ]"
                            :key="theme"
                            class="group relative cursor-pointer"
                            @click="appearanceForm.color_theme = theme"
                        >
                            <div
                                class="h-12 w-full rounded-lg border-2 shadow-sm transition-all duration-200"
                                :class="{
                                    'border-primary ring-primary/20 ring-2': appearanceForm.color_theme === theme,
                                    'border-border hover:border-primary/50 hover:shadow-md': appearanceForm.color_theme !== theme,
                                }"
                                :style="{
                                    backgroundColor: `var(--${theme}-${appearanceForm.color_shade})`,
                                }"
                            >
                                <div class="flex h-full items-center justify-center">
                                    <div
                                        v-if="appearanceForm.color_theme === theme"
                                        class="flex h-6 w-6 scale-100 transform items-center justify-center rounded-full bg-white shadow-lg transition-transform duration-200"
                                    >
                                        <div
                                            class="h-3 w-3 rounded-full"
                                            :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"
                                        ></div>
                                    </div>
                                    <div
                                        v-else
                                        class="flex h-6 w-6 scale-75 transform items-center justify-center rounded-full bg-white/80 opacity-0 shadow-sm transition-all duration-200 group-hover:scale-100 group-hover:opacity-100"
                                    >
                                        <div
                                            class="h-3 w-3 rounded-full"
                                            :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <p
                                class="mt-1 text-center text-xs font-medium capitalize transition-colors duration-200"
                                :class="{
                                    'text-primary': appearanceForm.color_theme === theme,
                                    'text-muted-foreground': appearanceForm.color_theme !== theme,
                                }"
                            >
                                {{ theme }}
                            </p>
                        </div>
                    </div>

                    <!-- Color Shade Slider -->
                    <div class="space-y-2">
                        <Label class="text-sm">Color Intensity</Label>
                        <div class="px-1">
                            <input
                                type="range"
                                min="50"
                                max="950"
                                step="50"
                                v-model="appearanceForm.color_shade"
                                class="slider h-3 w-full cursor-pointer appearance-none rounded-lg bg-gray-200"
                                :style="{
                                    background: `linear-gradient(to right, var(--${appearanceForm.color_theme}-50), var(--${appearanceForm.color_theme}-950))`,
                                }"
                                @input="
                                    (e: Event) => {
                                        const target = e.target as HTMLInputElement;
                                        const value = parseInt(target.value);
                                        const validShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
                                        const closest = validShades.reduce((prev, curr) =>
                                            Math.abs(curr - value) < Math.abs(prev - value) ? curr : prev,
                                        );
                                        appearanceForm.color_shade = closest;
                                    }
                                "
                            />
                        </div>
                        <div class="text-muted-foreground flex justify-between text-xs">
                            <span>Light</span>
                            <span class="font-medium">{{ appearanceForm.color_shade }}</span>
                            <span>Dark</span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        @click="
                            () => {
                                saveColorSettings();
                                showColorPicker = false;
                            }
                        "
                        :disabled="!canEdit"
                    >
                        Apply & Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Starter Dialog -->
        <Dialog v-model:open="showAddStarterDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Starter Message</DialogTitle>
                    <DialogDescription> Create a new conversation starter for your users </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label class="mb-2" for="starter-message">Message Text</Label>
                    <Textarea id="starter-message" v-model="starterMessageForm.text" placeholder="E.g., How can I track my order?" rows="3" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddStarterDialog = false"> Cancel </Button>
                    <Button :disabled="!canEdit || !starterMessageForm.text.trim()" @click="addStarterMessage">
                        <PlusCircle class="mr-2 h-4 w-4" />
                        Add Message
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- JavaScript API Documentation Dialog -->
        <Dialog v-model:open="showApiDocsDialog">
            <DialogContent class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Terminal class="h-5 w-5" />
                        JavaScript API
                    </DialogTitle>
                    <DialogDescription> Control the chat widget programmatically using these methods and events </DialogDescription>
                </DialogHeader>

                <div class="flex-1 space-y-6 overflow-y-auto pr-2">
                    <!-- Methods Table -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Zap class="h-4 w-4 text-amber-500" />
                            <h5 class="text-sm font-medium">Methods</h5>
                        </div>
                        <div class="overflow-hidden rounded-lg border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="text-muted-foreground px-3 py-2 text-left font-medium">Method</th>
                                        <th class="text-muted-foreground px-3 py-2 text-left font-medium">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="item in apiMethods" :key="item.method" class="hover:bg-muted/30 transition-colors">
                                        <td class="px-3 py-2">
                                            <code class="bg-muted text-primary rounded px-1.5 py-0.5 font-mono text-xs">{{ item.method }}</code>
                                        </td>
                                        <td class="text-muted-foreground px-3 py-2">{{ item.description }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Events Section -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Zap class="h-4 w-4 text-emerald-500" />
                            <h5 class="text-sm font-medium">Events</h5>
                        </div>
                        <p class="text-muted-foreground text-sm">
                            Subscribe to widget events using
                            <code class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">window.chatvia.on(event, callback)</code>
                        </p>
                        <div class="relative">
                            <div class="overflow-hidden rounded-lg bg-zinc-950">
                                <div class="flex items-center justify-between border-b border-zinc-800 bg-zinc-900 px-4 py-2">
                                    <span class="text-xs font-medium text-zinc-400">JavaScript</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200"
                                        @click="copyApiCode(eventsCodeExample)"
                                    >
                                        <Copy class="mr-1.5 h-3.5 w-3.5" />
                                        <span class="text-xs">Copy</span>
                                    </Button>
                                </div>
                                <pre
                                    class="overflow-x-auto p-4 text-sm"
                                ><code class="text-zinc-300 font-mono leading-relaxed"><span class="text-zinc-500">// When chat window opens</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'open'</span>, () =&gt; {
  <span class="text-sky-400">console</span>.<span class="text-emerald-400">log</span>(<span class="text-orange-300">'Chat opened'</span>);
});

<span class="text-zinc-500">// When chat window closes</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'close'</span>, () =&gt; {
  <span class="text-sky-400">console</span>.<span class="text-emerald-400">log</span>(<span class="text-orange-300">'Chat closed'</span>);
});

<span class="text-zinc-500">// When widget is ready</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'ready'</span>, () =&gt; {
  <span class="text-sky-400">console</span>.<span class="text-emerald-400">log</span>(<span class="text-orange-300">'Widget initialized'</span>);
});

<span class="text-zinc-500">// Unsubscribe from events</span>
<span class="text-violet-400">const</span> <span class="text-zinc-200">unsubscribe</span> = <span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'open'</span>, <span class="text-zinc-200">myCallback</span>);
<span class="text-zinc-200">unsubscribe</span>(); <span class="text-zinc-500">// Stop listening</span>

<span class="text-zinc-500">// Or use window.chatvia.off()</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">off</span>(<span class="text-orange-300">'open'</span>, <span class="text-zinc-200">myCallback</span>); <span class="text-zinc-500">// Remove specific callback</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">off</span>(<span class="text-orange-300">'open'</span>); <span class="text-zinc-500">// Remove all callbacks for this event</span></code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Example -->
                    <Alert class="bg-primary/5 border-primary/20">
                        <Info class="text-primary h-4 w-4" />
                        <AlertDescription class="text-sm">
                            <strong>Quick example:</strong> Open the chat when a button is clicked:
                            <code class="bg-muted mt-2 block rounded px-2 py-1 font-mono text-xs">
                                document.getElementById('help-btn').onclick = () =&gt; window.chatvia.open();
                            </code>
                        </AlertDescription>
                    </Alert>
                </div>

                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showApiDocsDialog = false"> Close </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>

<script setup lang="ts">
import AgentModelSelector from '@/components/agents/AgentModelSelector.vue';
import AgentPersonaEditor from '@/components/agents/AgentPersonaEditor.vue';
import PreviewModal from '@/components/PreviewModal.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Textarea } from '@/components/ui/textarea';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Bot,
    Brain,
    Code,
    Copy,
    Eye,
    Info,
    MessageSquare,
    Palette,
    PlusCircle,
    RotateCcw,
    Save,
    Settings,
    Shield,
    Terminal,
    Trash2,
    Type,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
    widgetSettings: any;
    appearanceSettings: any;
    embedScriptUrl: string;
    availableLanguages: Record<string, string>;
    starters: Array<{ id: number; text: string }>;
    has_permission: boolean;
}

const props = defineProps<Props>();

// Permission check
const canEdit = computed(() => props.has_permission);

// Separate forms for different setting groups
const appearanceForm = useForm({
    welcome_message: props.appearanceSettings.welcome_message,
    chat_window_title: props.appearanceSettings.chat_window_title,
    color_theme: props.appearanceSettings.color_theme,
    color_shade: props.appearanceSettings.color_shade,
});

const widgetForm = useForm({
    position: props.widgetSettings.position,
    show_on_load: props.widgetSettings.show_on_load,
    show_on_load_delay: props.widgetSettings.show_on_load_delay,
    window_size: props.widgetSettings.window_size,
    bubbles: props.widgetSettings.bubbles || [], // Initialize bubbles
    sites: props.widgetSettings.sites || [], // Initialize sites
});

// Menu items for navigation
const menuItems = [
    { value: 'text', label: 'Text', icon: Type },
    { value: 'persona', label: 'Persona', icon: Bot },
    { value: 'starters', label: 'Starters', icon: MessageSquare },
    { value: 'models', label: 'Model', icon: Brain },
    { value: 'colors', label: 'Colors', icon: Palette },
    { value: 'behavior', label: 'Behavior', icon: Settings },
    { value: 'embed', label: 'Embed', icon: Code },
];

// States
const activeTab = ref('text');
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const showPreviewModal = ref(false);
const showColorPicker = ref(false);
const previewKey = ref(0);
const showAddStarterDialog = ref(false);
const starterMessageForm = ref({ text: '' });
const starters = ref(props.starters || []);
const showApiDocsDialog = ref(false);
const selectedLanguage = ref('auto');

// API documentation data
const apiMethods = [
    { method: 'window.chatvia.open()', description: 'Opens the chat window' },
    { method: 'window.chatvia.close()', description: 'Closes the chat window' },
    { method: 'window.chatvia.toggle()', description: 'Toggles open/close state' },
    { method: 'window.chatvia.isOpen()', description: 'Returns boolean if open' },
    { method: 'window.chatvia.send(msg)', description: 'Sends a message programmatically' },
    { method: 'window.chatvia.newChat()', description: 'Starts a new chat thread' },
    { method: 'window.chatvia.destroy()', description: 'Destroys widget & cleans up' },
    { method: 'window.chatvia.on(event, fn)', description: 'Subscribe to events (open, close, ready)' },
    { method: 'window.chatvia.off(event, fn)', description: 'Unsubscribe from events' },
];

const eventsCodeExample = `// When chat window opens
window.chatvia.on('open', () => {
  console.log('Chat opened');
});

// When chat window closes
window.chatvia.on('close', () => {
  console.log('Chat closed');
});

// When widget is ready
window.chatvia.on('ready', () => {
  console.log('Widget initialized');
});

// Unsubscribe from events
const unsubscribe = window.chatvia.on('open', myCallback);
unsubscribe(); // Stop listening

// Or use window.chatvia.off()
window.chatvia.off('open', myCallback); // Remove specific callback
window.chatvia.off('open'); // Remove all callbacks for this event`;

const copyApiCode = async (code: string) => {
    try {
        await navigator.clipboard.writeText(code);
        toast.success('Code copied to clipboard!');
    } catch (error) {
        console.error('Failed to copy code:', error);
        toast.error('Failed to copy code. Please try again.');
    }
};

// Computed properties for header button states
const hasUnsavedChanges = computed(() => {
    // Check if any form data has changed from initial values
    const appearanceChanged =
        appearanceForm.welcome_message !== props.appearanceSettings.welcome_message ||
        appearanceForm.chat_window_title !== props.appearanceSettings.chat_window_title ||
        appearanceForm.color_theme !== props.appearanceSettings.color_theme ||
        appearanceForm.color_shade !== props.appearanceSettings.color_shade;

    const widgetChanged =
        widgetForm.position !== props.widgetSettings.position ||
        widgetForm.show_on_load !== props.widgetSettings.show_on_load ||
        widgetForm.show_on_load_delay !== props.widgetSettings.show_on_load_delay ||
        widgetForm.window_size !== props.widgetSettings.window_size ||
        JSON.stringify(widgetForm.bubbles) !== JSON.stringify(props.widgetSettings.bubbles || []) ||
        JSON.stringify(widgetForm.sites) !== JSON.stringify(props.widgetSettings.sites || []);

    return appearanceChanged || widgetChanged;
});

const hasFormErrors = computed(() => {
    return Object.keys(appearanceForm.errors).length > 0 || Object.keys(widgetForm.errors).length > 0;
});

const hasSecurityChanges = computed(() => {
    return JSON.stringify(widgetForm.sites) !== JSON.stringify(props.widgetSettings.sites || []);
});

const updateWidgetSettings = async () => {
    isSubmitting.value = true;
    showSuccessMessage.value = false;

    try {
        // Send text/appearance settings
        await new Promise((resolve, reject) => {
            appearanceForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
                preserveScroll: true,
                onSuccess: () => resolve(true),
                onError: () => reject(new Error('Appearance settings failed')),
            });
        });

        // Then send widget settings (bubbles)
        const textForm = useForm({
            bubbles: widgetForm.bubbles,
        });

        await new Promise((resolve, reject) => {
            textForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
                preserveScroll: true,
                onSuccess: () => resolve(true),
                onError: () => reject(new Error('Widget settings failed')),
            });
        });

        // Both requests succeeded
        isSubmitting.value = false;
        showSuccessMessage.value = true;

        // Refresh preview after successful save
        previewKey.value += 1;

        setTimeout(() => {
            showSuccessMessage.value = false;
        }, 3000);
    } catch (error) {
        isSubmitting.value = false;
        console.error('Failed to save settings:', error);
    }
};

const saveSecuritySettings = () => {
    const securityForm = useForm({
        sites: widgetForm.sites,
    });

    securityForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Security settings updated');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update security settings');
        },
    });
};

const saveColorSettings = () => {
    const colorForm = useForm({
        color_theme: appearanceForm.color_theme,
        color_shade: appearanceForm.color_shade,
    });

    colorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Color updated successfully');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update color');
        },
    });
};

const saveBehaviorSettings = () => {
    const behaviorForm = useForm({
        position: widgetForm.position,
        show_on_load: widgetForm.show_on_load,
        show_on_load_delay: widgetForm.show_on_load_delay,
        window_size: widgetForm.window_size,
    });

    behaviorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Behavior settings updated');
        },
        onError: () => {
            toast.error('Failed to update behavior settings');
        },
    });
};

const openPreview = () => {
    showPreviewModal.value = true;
};

const embedSnippet = computed(() => {
    const scriptLines = [
        '<script',
        `  id="chatbot-embed-script"`,
        `  src="${props.embedScriptUrl}"`,
        `  data-agent-id="${props.agent.external_id}"`,
        `  data-language="${selectedLanguage.value}"`,
        '  defer>',
        '</scr' + 'ipt>',
    ];
    return scriptLines.join('\n');
});

const copyEmbedSnippet = async () => {
    try {
        await navigator.clipboard.writeText(embedSnippet.value);
        toast.success('Embed code copied');
    } catch (e) {
        toast.error('Failed to copy');
    }
};

// Live preview URL that updates with current settings
const previewUrl = computed(() => {
    const baseUrl = route('agents.widget.preview', { agent: props.agent.external_id });
    return `${baseUrl}?language=${selectedLanguage.value}`;
});

// Full preview URL - just adds full-preview=true to the base URL
const fullPreviewUrl = computed(() => {
    const baseUrl = route('agents.widget.preview', { agent: props.agent.external_id });
    return `${baseUrl}?full-preview=true&language=${selectedLanguage.value}`;
});

// Methods for preview management
const refreshPreview = () => {
    previewKey.value += 1;
};

const openFullPreview = () => {
    showPreviewModal.value = true;
};

// Starters functionality
const addStarterMessage = () => {
    axios
        .post(route('agents.starters.store', props.agent.external_id), { text: starterMessageForm.value.text })
        .then((response) => {
            showAddStarterDialog.value = false;
            starters.value.push({
                id: response.data.id,
                text: response.data.text,
            });
            starterMessageForm.value.text = '';
            toast.success('Starter message added');
            previewKey.value += 1;
        })
        .catch(() => {
            toast.error('Failed to add starter message');
        });
};

const removeStarter = (id: number) => {
    axios
        .delete(route('agents.starters.destroy', { agent: props.agent.external_id, id }))
        .then(() => {
            starters.value = starters.value.filter((starter) => starter.id !== id);
            toast.success('Starter message removed');
            previewKey.value += 1;
        })
        .catch(() => {
            toast.error('Failed to remove starter message');
        });
};

// Models functionality
// Preview updates only when Save is clicked - removed auto-update watcher
</script>
