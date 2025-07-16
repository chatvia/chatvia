export interface Message {
  id?: string | number
  content: string
  role: 'user' | 'assistant'
  model?: string
  created_at?: string
  updated_at?: string
  feedback?: boolean
  sources?: any[]
}

export interface AIModel {
  id: string;
  name: string;
  context_window: number;
  icon: string;
  description: string;
  features?: string[];
  location: string;
  reasoning: boolean;
  user_allowed_to_use: boolean;
  is_selected: boolean;
}

export interface Conversation {
  id: string
  title: string
  created_at: string
  updated_at: string
  messages?: Message[]
}

export interface User {
  id: number
  name: string
  email: string
  avatar?: string
}
