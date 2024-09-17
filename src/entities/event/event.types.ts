export interface TEvent {
    id?: string;
    name: string;
    description?: string;
    players?: string[]; // Array of player UUIDs
    effects?: string[]; // Array of effect UUIDs
    startDate?: string;
    endDate?: string;
    location?: string;
  }
