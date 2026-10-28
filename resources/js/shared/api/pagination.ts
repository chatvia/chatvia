/**
 * One page of a server list paginated with Laravel's `paginate()`, as JSON:
 * the rows of the page, the page numbers and the total number of rows.
 */
export interface LengthAwarePage<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
}
