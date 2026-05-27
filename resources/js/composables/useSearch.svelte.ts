export function useSearch(
    getInitialValue: () => string,
    onNavigate: (value: string) => void,
) {
    let search = $state(getInitialValue());
    let timeout: ReturnType<typeof setTimeout>;

    $effect(() => {
        search = getInitialValue();
    });

    function doSearch(value: string) {
        search = value;
        clearTimeout(timeout);
        timeout = setTimeout(() => onNavigate(value), 300);
    }

    function clearSearch() {
        search = '';
        onNavigate('');
    }

    return {
        get search() {
            return search;
        },
        doSearch,
        clearSearch,
    };
}
