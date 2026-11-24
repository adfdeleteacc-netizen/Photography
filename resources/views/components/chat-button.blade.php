{{-- Needs a booking object `b` in the surrounding Alpine scope (e.g. inside x-for="b in rows"). --}}
<div x-data="chat(b.id, {{ auth()->id() }})" class="inline-block">
    <button type="button" class="btn-ghost" @click="toggle()">💬 Chat</button>
    <div x-show="open" x-cloak class="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-black/40 p-4" @keydown.escape.window="open && toggle()">
        <div class="card w-full max-w-md flex flex-col h-[28rem]" @click.outside="toggle()">
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <b class="text-sm" x-text="'Chat · ' + b.reference"></b>
                <button class="text-slate-400 hover:text-slate-700" @click="toggle()">✕</button>
            </div>
            <div x-ref="list" class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50">
                <template x-for="m in messages" :key="m.id">
                    <div :class="mine(m) ? 'text-right' : ''">
                        <span class="inline-block max-w-[80%] rounded-2xl px-3 py-2 text-sm text-left"
                              :class="mine(m) ? 'bg-indigo-600 text-white' : 'bg-white border'" x-text="m.body"></span>
                        <div class="text-[10px] text-slate-400 mt-0.5" x-text="(mine(m) ? 'You' : m.sender?.name) + ' · ' + fmtDate(m.created_at)"></div>
                    </div>
                </template>
                <p x-show="!messages.length" class="text-center text-sm text-slate-400 mt-8">No messages yet. Say hello 👋</p>
            </div>
            <div class="p-3 border-t flex gap-2">
                <input class="input" x-model="body" placeholder="Type a message…" @keydown.enter.prevent="send()">
                <button class="btn-primary" @click="send()">Send</button>
            </div>
            <p class="px-4 pb-2 text-[10px] text-slate-400">Phone numbers and emails are hidden to keep bookings and payments on the platform.</p>
        </div>
    </div>
</div>
