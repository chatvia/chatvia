import { describe, it, expect, beforeAll, beforeEach, vi } from 'vitest';
import { initI18n } from '../src/i18n.js';
import { createInlineForm } from '../src/forms.js';

// forms.js dynamically imports ./api.js inside submit(); intercept it so no real
// network call happens and we can assert the request shape.
const apiMock = vi.fn();
vi.mock('../src/api.js', () => ({ default: (...args) => apiMock(...args) }));

const widget = {
  agentId: 'agent-1',
  sessionId: 'sess-1',
  getCurrentActiveConversationId: () => 'conv-1',
  messageHistory: []
};

function baseForm(overrides = {}) {
  return {
    form_instance_id: 'fi-1',
    title: 'Contact us',
    submit_label: 'Send',
    success_message: 'Thanks!',
    status: 'pending',
    schema: [
      { $formkit: 'text', name: 'name', label: 'Name', validation: 'required' },
      { $formkit: 'email', name: 'email', label: 'Email', validation: 'required|email' },
      {
        $formkit: 'select',
        name: 'topic',
        label: 'Topic',
        options: [{ value: 'sales', label: 'Sales' }]
      },
      { $formkit: 'textarea', name: 'msg', label: 'Message' }
    ],
    ...overrides
  };
}

function submitForm(el) {
  el.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
}

describe('inline form artifact', () => {
  beforeAll(async () => {
    await initI18n({ language: 'en' });
  });

  beforeEach(() => {
    apiMock.mockReset();
    widget.messageHistory = [];
  });

  it('renders one control per schema field', () => {
    const el = createInlineForm(widget, baseForm(), 'msg-1');

    expect(el.querySelector('input[name="name"]')).toBeTruthy();
    expect(el.querySelector('input[name="email"]')?.type).toBe('email');
    expect(el.querySelector('select')).toBeTruthy();
    expect(el.querySelector('textarea')).toBeTruthy();
    expect(el.querySelector('.chatvia-widget-form-submit')?.textContent).toBe('Send');
  });

  it('shows a read-only success view (no form) when already submitted', () => {
    const el = createInlineForm(widget, baseForm({ status: 'submitted' }), 'msg-1');

    expect(el.querySelector('.chatvia-widget-form-success')).toBeTruthy();
    expect(el.querySelector('form')).toBeNull();
  });

  it('blocks submit and flags the field when a required value is missing', () => {
    const el = createInlineForm(widget, baseForm(), 'msg-1');

    submitForm(el);

    expect(apiMock).not.toHaveBeenCalled();
    expect(el.querySelector('.chatvia-widget-form-field-error')?.textContent).not.toBe('');
  });

  it('posts the answers and flips to the success view on 200', async () => {
    apiMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success_message: 'Got it' })
    });
    widget.messageHistory = [
      {
        id: 'msg-1',
        metadata: {
          artifacts: [{ kind: 'form', data: { form_instance_id: 'fi-1', status: 'pending' } }]
        }
      }
    ];

    const el = createInlineForm(widget, baseForm(), 'msg-1');
    el.querySelector('input[name="name"]').value = 'Jane';
    el.querySelector('input[name="email"]').value = 'jane@acme.com';
    el.querySelector('select').value = 'sales';

    submitForm(el);

    await vi.waitFor(() => expect(el.querySelector('.chatvia-widget-form-success')).toBeTruthy());

    expect(apiMock).toHaveBeenCalledTimes(1);
    const req = apiMock.mock.calls[0][0];
    expect(req.endpoint).toBe('widget/forms/fi-1/submit');
    expect(req.method).toBe('POST');
    const body = JSON.parse(req.body);
    expect(body.fields).toEqual({ name: 'Jane', email: 'jane@acme.com', topic: 'sales', msg: '' });
    expect(body.assistant_message_id).toBe('msg-1');
    // success message from the response + stored artifact flipped for in-session reload
    expect(el.querySelector('.chatvia-widget-form-success').textContent).toContain('Got it');
    expect(widget.messageHistory[0].metadata.artifacts[0].data.status).toBe('submitted');
  });

  it('treats a 409 (already submitted) as done', async () => {
    apiMock.mockResolvedValue({ ok: false, status: 409, json: async () => ({}) });

    const el = createInlineForm(widget, baseForm({ schema: [] }), 'msg-1');
    submitForm(el);

    await vi.waitFor(() => expect(el.querySelector('.chatvia-widget-form-success')).toBeTruthy());
  });
});
