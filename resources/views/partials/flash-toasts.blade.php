{{-- Flashed messages, shown as toasts.

     A flashed message has to reach <flux:toast> from outside Livewire: a
     plain controller redirect -- or a Livewire redirect, which ends the
     component that would have dispatched -- leaves nothing to dispatch
     from, so it goes through Flux's standalone JS API instead.

     Two different moments have to work:

     alpine:init fires the instant Alpine.start() begins -- BEFORE Alpine
     has walked the DOM and wired up the toast host component's
     "toast-show" listener. Calling Flux.toast() synchronously there
     dispatches the event into the void because nothing is listening yet,
     so the call is queued to the next tick.

     After a wire:navigate visit Alpine is already running and alpine:init
     will never fire again. This script arrives with the swapped-in body,
     so it has to call straight through instead of waiting for an event
     that has already been and gone -- which is exactly the case for every
     redirect a Livewire form makes after saving something. --}}
@foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
    @if (session($key))
        <script>
            (() => {
                const show = () => setTimeout(() => window.Flux?.toast({
                    text: @js(session($key)),
                    variant: @js($variant),
                }));

                window.Alpine ? show() : document.addEventListener('alpine:init', show);
            })();
        </script>
    @endif
@endforeach
