/**
 * Keydown guard for the add-on quantity inputs: lets digits, editing keys
 * (Backspace, Tab, Escape, Enter, Delete) and Ctrl+A/C/V/X through and blocks
 * everything else, so a quantity can never hold a sign, decimal or letter.
 */
export function handleNumericKeyPress(event: KeyboardEvent): void {
    if (
        [8, 9, 27, 13, 46].indexOf(event.keyCode) !== -1 ||
        (event.keyCode === 65 && event.ctrlKey === true) ||
        (event.keyCode === 67 && event.ctrlKey === true) ||
        (event.keyCode === 86 && event.ctrlKey === true) ||
        (event.keyCode === 88 && event.ctrlKey === true)
    ) {
        return;
    }
    if ((event.shiftKey || event.keyCode < 48 || event.keyCode > 57) && (event.keyCode < 96 || event.keyCode > 105)) {
        event.preventDefault();
    }
}
