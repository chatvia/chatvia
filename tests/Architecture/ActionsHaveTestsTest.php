<?php

/**
 * Guardrail enforcing "every Action lands with a test."
 *
 * Goal: prevent the Action-test coverage ratio from regressing. The explicit
 * allowlist below pins the set of currently-untested Actions; the live
 * tested/total ratio is printed to STDERR when this test runs, so the figure
 * can never drift out of sync with reality (it used to be a hand-typed comment
 * that went stale). Any NEW Action without a test fails CI loudly, forcing the
 * author to either write the test or make an explicit decision to add the new
 * name to the allowlist (which shows up in code review).
 *
 * The allowlist is meant to SHRINK over time, never grow. When you add a
 * test for an Action in the list, this test will tell you to remove it.
 *
 * Detection is name-based: an Action `Foo\BarAction` is considered tested
 * if any file under tests/ contains the whole-word `BarAction` (use of the
 * class, type-hint, or `BarAction::class` reference all count). Multi-Action
 * test files (e.g. TakeoverAndReturnConversationActionsTest covers two
 * Actions in one file) work without renaming.
 */
const UNTESTED_ACTIONS_ALLOWLIST = [
    'AcceptAgentInvitationAction',
    'AcceptTeamInvitationAction',
    'AddAgentYoutubeVideoAction',
    'AdminDeleteConversationAction',
    'AgentSourceAnalyzeFileAction',
    'AnalyzeMessageAction',
    'AssignUserToRoleAction',
    'AttachExistingTopicToAgentAction',
    'AttachFunctionToAgentAction',
    'AttachGenericConnectorToAgentAction',
    'AttachSourceTextToAgentAction',
    'AttachWebsiteToAgentAction',
    'AutoRechargeCreditsAction',
    'BroadcastChatTypingAction',
    'BuildConversationPulseAction',
    'BuildWidgetSettingsAction',
    'BulkDeleteAgentLeadsAction',
    'BulkDeleteWebsiteUrlsAction',
    'BuyExtraMessageCreditsAction',
    'CancelCustomDomainAction',
    'CancelExtraAgentsAction',
    'CancelTeamInvitationAction',
    'CrawlWebsiteAction',
    'CreateAgentAction',
    'CreateAgentFunctionAction',
    'CreateAgentQaAction',
    'CreateAgentSourceTextAction',
    'CreateAgentTopicAction',
    'CreateApiAgentAction',
    'CreateApiFunctionAction',
    'CreateConversationAction',
    'CreateOrReuseWidgetSessionAction',
    'CreatePromptAction',
    'CreateQaFromMessageReviseAction',
    'CreateSourceSummaryAction',
    'CreateTicketAction',
    'DeleteAgentAction',
    'DeleteAgentFunctionAction',
    'DeleteAgentSettingAction',
    'DeleteAgentSourceTextAction',
    'DeleteAgentUploadedFilesAction',
    'DeleteAgentWebsiteUrlAction',
    'DeleteAgentYoutubeVideoAction',
    'DeleteMcpServerAction',
    'DeletePromptAction',
    'DeleteSourceFileFromAgentAction',
    'DeleteSourceWebsiteAction',
    'DeleteTeamRoleAction',
    'DetachFunctionFromAgentAction',
    'DetachGenericConnectorFromAgentAction',
    'DetachSourceTextFromAgentAction',
    'DetachTopicFromAgentAction',
    'DetachWebsiteFromAgentAction',
    'EmbedAgentFunctionAction',
    'EmbedMcpToolsAction',
    'EnhanceDocumentChunkAction',
    'ExportAgentLeadsToCsvAction',
    'FindWidgetConversationAction',
    'GetDashboardStatsAction',
    'GrantAgentMembershipAction',
    'IngestDocumentAction',
    'IngestDocumentChunkAction',
    'IngestMediaAction',
    'IngestTextAction',
    'InitializeWidgetSessionAction',
    'InviteAgentMemberAction',
    'InviteTeamMemberAction',
    'ListAccountConnectorsAction',
    'ListAgentConnectorsAction',
    'ListAgentConversationsAction',
    'ListAgentFunctionsAction',
    'ListAgentVisitorCountriesAction',
    'ListAttachableTextsForAgentAction',
    'ListConnectorAgentAssignmentAction',
    'ListConnectorToolsAction',
    'ListConversationMessagesAction',
    'ListFunctionAgentAssignmentAction',
    'ListPromptsForManagementPageAction',
    'ListPromptsForPickerAction',
    'ListUserKnowledgeFilesAction',
    'ListWidgetConversationsAction',
    'ProcessAgentUploadedFilesAction',
    'ProvisionCustomMcpServerAction',
    'RecordConversationActivityAction',
    'RecordMessageSentAction',
    'RefreshAgentConnectorCatalogAction',
    'RegenerateMessageAction',
    'RemoveProfilePhotoAction',
    'RenameAgentAction',
    'ReplyToTicketAction',
    'ResolveAgentPromptIdAction',
    'ResolveOwnedTeamAction',
    'RetrainAgentWebsiteUrlAction',
    'RetrainAgentYoutubeVideoAction',
    'RetrainSourceWebsiteAction',
    'RevokeAgentMembershipAction',
    'SendHandoffAdminMessageAction',
    'SendVisitorHandoffMessageAction',
    'SetMcpToolPermissionAction',
    'SocialLoginAction',
    'StartAgentWebsiteCrawlAction',
    'StartWebsiteIngestionAction',
    'StoreAttachmentAction',
    'SubscribeCustomDomainAction',
    'SubscribeExtraAgentsAction',
    'ToggleAgentFunctionAction',
    'ToggleMcpServerAction',
    'TrainWebsiteUrlAction',
    'TranscribeAudioAction',
    'TwoFactorAwareLoginAction',
    'UnlinkSourceFileFromAgentAction',
    'UpdateAgentDomainAction',
    'UpdateAgentFunctionAction',
    'UpdateAgentLeadsSettingsAction',
    'UpdateAgentLlmAction',
    'UpdateAgentQaAction',
    'UpdateAgentSettingsAction',
    'UpdateAgentSettingsGroupAction',
    'UpdateAgentSourceTextAction',
    'UpdateApiAgentAction',
    'UpdateApiFunctionAction',
    'UpdateAutoRechargeAction',
    'UpdateIngestionJobStatusAction',
    'UpdatePromptAction',
    'UpdateTeamAction',
    'UpdateTeamRoleAction',
    'UpdateTopicAction',
    'UploadKnowledgeSourceFileAction',
];

/** Discover all Action class files in src/ and return their basenames sans .php */
function allActionClassNames(): array
{
    $files = [];
    $base = __DIR__.'/../../src';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with((string) $file, 'Action.php')) {
            $files[] = $file->getBasename('.php');
        }
    }
    sort($files);

    return $files;
}

/** Is there a test file under tests/ that references this Action by name? */
function actionHasTest(string $actionShortName): bool
{
    // Cache the discovery of every test file's text once per process. EXCLUDE
    // this file from the scan — the allowlist below carries every Action's
    // name as a plain string, which would otherwise match every name and
    // make the test useless.
    static $combined = null;
    if ($combined === null) {
        $self = realpath(__FILE__);
        $combined = '';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/..'));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with((string) $file, '.php')) {
                continue;
            }
            if (realpath($file->getPathname()) === $self) {
                continue;
            }
            $combined .= file_get_contents($file->getPathname()).PHP_EOL;
        }
    }

    return preg_match('/\b'.preg_quote($actionShortName, '/').'\b/', $combined) === 1;
}

it('every Action either has a test or is on the explicit allowlist', function (): void {
    $allowlist = UNTESTED_ACTIONS_ALLOWLIST;
    $allActions = allActionClassNames();

    $currentlyUntested = array_values(array_filter(
        $allActions,
        fn (string $name): bool => ! actionHasTest($name),
    ));

    // Self-truthing coverage figure — printed every run so no stale comment
    // can misrepresent the real ratio (see the class docblock).
    $tested = count($allActions) - count($currentlyUntested);
    fwrite(STDERR, sprintf(
        "\nAction test coverage: %d/%d (%.0f%%) tested, %d allowlisted\n",
        $tested,
        count($allActions),
        count($allActions) > 0 ? 100 * $tested / count($allActions) : 0,
        count($allowlist),
    ));

    // (a) Drift up: new Action lacks a test AND is not on the allowlist.
    $newUntested = array_values(array_diff($currentlyUntested, $allowlist));
    expect($newUntested)->toBe([], implode("\n", [
        '',
        'New Action(s) without a test:',
        ...array_map(fn (string $n): string => "  - {$n}", $newUntested),
        '',
        'Either add a test (preferred — see CLAUDE.md test conventions)',
        'or add the class short-name to UNTESTED_ACTIONS_ALLOWLIST in this file.',
    ]));

    // (b) Drift down (cleanup signal): an entry on the allowlist now has a
    // test, so the allowlist row should be removed. This is intentionally a
    // hard failure so the list shrinks monotonically.
    $nowTested = array_values(array_intersect($allowlist, array_diff($allActions, $currentlyUntested)));
    expect($nowTested)->toBe([], implode("\n", [
        '',
        'Action(s) on the allowlist now have tests — remove them from',
        'UNTESTED_ACTIONS_ALLOWLIST so the list shrinks over time:',
        ...array_map(fn (string $n): string => "  - {$n}", $nowTested),
        '',
    ]));
});
