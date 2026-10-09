<script setup lang="ts">
// Add / edit dialog for one eval case. Opening it with `editing` set fills the
// form from that case; opening it with `editing` null starts a fresh case. The
// form lives as long as the page, so this component stays mounted while closed.
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Braces, Check, FileText, LoaderCircle, MessageSquare, Plus, Regex, Scale, Search, Tag, Wrench, X } from 'lucide-vue-next';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { EvalCase } from './types';
import { useEvalCaseForm } from './useEvalCaseForm';
import { useScorers } from './useScorers';

const props = defineProps<{
    agentExternalId: string;
    scorerTypes: string[];
    editing: EvalCase | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { scorerIcon, scorerLabel, scorerHelp } = useScorers();
const { form, editingId, configError, startCreate, startEdit, submit } = useEvalCaseForm(props.agentExternalId);

// A JSON syntax example, not prose — kept out of i18n because vue-i18n reserves
// `{`, `}` and `@`, which a JSON object literal is full of.
const TOOL_ARGS_PLACEHOLDER = '{\n  "to": "support@acme.com"\n}';

// Fill the form each time the dialog opens, before it renders.
watch(open, (isOpen) => {
    if (!isOpen) return;
    if (props.editing) startEdit(props.editing);
    else startCreate();
});

const save = () => submit(() => (open.value = false));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    editingId ? t('agents.evals.index.case_dialog.edit_title') : t('agents.evals.index.case_dialog.add_title')
                }}</DialogTitle>
                <DialogDescription>{{ t('agents.evals.index.case_dialog.description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <Label for="case-name">{{ t('agents.evals.index.case_dialog.name_label') }}</Label>
                    <div class="relative">
                        <Tag class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input id="case-name" v-model="form.name" :placeholder="t('agents.evals.index.case_dialog.name_placeholder')" class="ps-9" />
                    </div>
                    <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1.5">
                    <Label for="case-input">{{ t('agents.evals.index.case_dialog.input_label') }}</Label>
                    <div class="relative">
                        <MessageSquare class="text-muted-foreground pointer-events-none absolute start-3 top-3 h-4 w-4" />
                        <Textarea
                            id="case-input"
                            v-model="form.input"
                            rows="3"
                            :placeholder="t('agents.evals.index.case_dialog.input_placeholder')"
                            class="ps-9"
                        />
                    </div>
                    <p v-if="form.errors.input" class="text-destructive text-xs">{{ form.errors.input }}</p>
                </div>

                <div class="space-y-1.5">
                    <Label>{{ t('agents.evals.index.case_dialog.scorer_label') }}</Label>
                    <Select v-model="form.scorer_type">
                        <SelectTrigger>
                            <SelectValue :placeholder="t('agents.evals.index.case_dialog.scorer_placeholder')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="type in props.scorerTypes" :key="type" :value="type">
                                <span class="flex items-center gap-2">
                                    <component :is="scorerIcon(type)" class="text-muted-foreground h-3.5 w-3.5" />
                                    {{ scorerLabel(type) }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-muted-foreground text-xs">{{ scorerHelp(form.scorer_type) }}</p>
                </div>

                <!-- Scorer-specific config -->
                <template v-if="form.scorer_type === 'contains'">
                    <div class="space-y-1.5">
                        <Label for="cfg-value">{{ t('agents.evals.index.case_dialog.expected_substring') }}</Label>
                        <div class="relative">
                            <Search class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input
                                id="cfg-value"
                                v-model="form.value"
                                :placeholder="t('agents.evals.index.case_dialog.expected_substring_placeholder')"
                                class="ps-9"
                            />
                        </div>
                        <p v-if="configError('value')" class="text-destructive text-xs">{{ configError('value') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch id="cfg-cs" v-model="form.case_sensitive" />
                        <Label for="cfg-cs" class="cursor-pointer font-normal">{{ t('agents.evals.index.case_dialog.case_sensitive') }}</Label>
                    </div>
                </template>

                <div v-else-if="form.scorer_type === 'regex'" class="space-y-1.5">
                    <Label for="cfg-pattern">{{ t('agents.evals.index.case_dialog.pattern_label') }}</Label>
                    <div class="relative">
                        <Regex class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            id="cfg-pattern"
                            v-model="form.pattern"
                            :placeholder="t('agents.evals.index.case_dialog.pattern_placeholder')"
                            class="ps-9 font-mono"
                        />
                    </div>
                    <p v-if="configError('pattern')" class="text-destructive text-xs">{{ configError('pattern') }}</p>
                </div>

                <div v-else-if="form.scorer_type === 'json_schema'" class="space-y-1.5">
                    <Label for="cfg-required">{{ t('agents.evals.index.case_dialog.required_keys') }}</Label>
                    <div class="relative">
                        <Braces class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            id="cfg-required"
                            v-model="form.required"
                            :placeholder="t('agents.evals.index.case_dialog.required_keys_placeholder')"
                            class="ps-9"
                        />
                    </div>
                    <p class="text-muted-foreground text-xs">{{ t('agents.evals.index.case_dialog.required_keys_hint') }}</p>
                </div>

                <div v-else-if="form.scorer_type === 'llm_judge'" class="space-y-1.5">
                    <Label for="cfg-criteria">{{ t('agents.evals.index.case_dialog.grading_criteria') }}</Label>
                    <div class="relative">
                        <Scale class="text-muted-foreground pointer-events-none absolute start-3 top-3 h-4 w-4" />
                        <Textarea
                            id="cfg-criteria"
                            v-model="form.criteria"
                            rows="3"
                            :placeholder="t('agents.evals.index.case_dialog.grading_criteria_placeholder')"
                            class="ps-9"
                        />
                    </div>
                    <p v-if="configError('criteria')" class="text-destructive text-xs">
                        {{ configError('criteria') }}
                    </p>
                </div>

                <template v-else-if="form.scorer_type === 'tool_call'">
                    <div class="space-y-1.5">
                        <Label for="cfg-tool">{{ t('agents.evals.index.case_dialog.expected_tool') }}</Label>
                        <div class="relative">
                            <Wrench class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input
                                id="cfg-tool"
                                v-model="form.tool"
                                :placeholder="t('agents.evals.index.case_dialog.expected_tool_placeholder')"
                                class="ps-9 font-mono"
                            />
                        </div>
                        <p class="text-muted-foreground text-xs">{{ t('agents.evals.index.case_dialog.expected_tool_hint') }}</p>
                        <p v-if="configError('tool')" class="text-destructive text-xs">{{ configError('tool') }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="cfg-tool-args">{{ t('agents.evals.index.case_dialog.expected_arguments') }}</Label>
                        <Textarea id="cfg-tool-args" v-model="form.tool_arguments" rows="3" :placeholder="TOOL_ARGS_PLACEHOLDER" class="font-mono" />
                        <p class="text-muted-foreground text-xs">{{ t('agents.evals.index.case_dialog.expected_arguments_hint') }}</p>
                        <p v-if="configError('arguments')" class="text-destructive text-xs">{{ configError('arguments') }}</p>
                    </div>
                </template>

                <div v-if="['exact', 'llm_judge'].includes(form.scorer_type)" class="space-y-1.5">
                    <Label for="cfg-expected">
                        {{
                            form.scorer_type === 'exact'
                                ? t('agents.evals.index.case_dialog.expected_answer')
                                : t('agents.evals.index.case_dialog.reference_answer')
                        }}
                    </Label>
                    <div class="relative">
                        <FileText class="text-muted-foreground pointer-events-none absolute start-3 top-3 h-4 w-4" />
                        <Textarea id="cfg-expected" v-model="form.expected_output" rows="2" class="ps-9" />
                    </div>
                    <p v-if="form.errors.expected_output" class="text-destructive text-xs">{{ form.errors.expected_output }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <Switch id="cfg-active" v-model="form.active" />
                    <Label for="cfg-active" class="cursor-pointer font-normal">{{ t('agents.evals.index.case_dialog.active_label') }}</Label>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="form.processing" @click="open = false">
                    <X class="me-2 h-4 w-4" />
                    {{ t('common.cancel') }}
                </Button>
                <Button :disabled="form.processing" @click="save">
                    <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                    <Check v-else-if="editingId" class="me-2 h-4 w-4" />
                    <Plus v-else class="me-2 h-4 w-4" />
                    {{ editingId ? t('agents.evals.index.case_dialog.save') : t('agents.evals.index.add_case') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
