<div>
    <div class="relative bg-slate-900 rounded-xs py-10 px-8 md:p-10 md:mt-24">

        <!-- Background illustration -->
        <div class="absolute right-0 bottom-0 pointer-events-none hidden lg:block" aria-hidden="true">
            <img src="{{ asset('images/cta-image.png') }}" width="456" height="337" alt="Free trial" />
        </div>

        <div class="relative flex flex-col lg:flex-row justify-between items-center">

            <!-- CTA content -->
            <div class="text-center lg:text-left lg:max-w-xl">
                <h3 class="h3 font-playfair-display text-slate-100 mb-8 text-4xl">Creates custom GPTs in minutes - powered by <span class="text-emerald-500">your data</span>.</h3>
                <div class="mb-3">
                    <a class="btn text-white bg-blue-600 hover:bg-blue-700 group" href="{{ route('home') }}#pricing">
                        Start free trial <span class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1"></span>
                    </a>
                </div>
                <div class="text-sm italic text-slate-500">{{ config('billing.trial.days') }}-day free trial. No credit card required!</div>
            </div>

        </div>

    </div>
</div>
