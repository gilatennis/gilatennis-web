class SearchController {
    search = $state('');
    #timeout: ReturnType<typeof setTimeout> | null = null;
    #onNavigate: (value: string) => void;

    constructor(
        getInitial: () => string,
        onNavigate: (value: string) => void,
    ) {
        this.search = getInitial();
        this.#onNavigate = onNavigate;
    }

    doSearch(value: string): void {
        this.search = value;
        if (this.#timeout) clearTimeout(this.#timeout);
        this.#timeout = setTimeout(() => this.#onNavigate(value), 300);
    }

    clearSearch(): void {
        this.search = '';
        this.#onNavigate('');
    }
}

export function useSearch(
    getInitialValue: () => string,
    onNavigate: (value: string) => void,
): SearchController {
    return new SearchController(getInitialValue, onNavigate);
}
