import { Form, Head } from '@inertiajs/react';

export default function Login() {
    return (
        <>
            <Head title="Entrar" />
            <main className="relative grid min-h-screen place-items-center overflow-hidden bg-[#f4f6fa] px-5 text-slate-800">
                <div className="absolute left-1/2 top-[-12rem] h-[32rem] w-[48rem] -translate-x-1/2 rounded-full bg-indigo-200/35 blur-3xl" />
                <section className="relative w-full max-w-md rounded-3xl border border-slate-200/80 bg-white/95 p-8 shadow-[0_24px_70px_rgba(15,23,42,0.10)] backdrop-blur-xl">
                    <div className="mb-8 flex items-center gap-3">
                        <div className="grid size-11 place-items-center rounded-xl bg-slate-800 text-lg font-black text-white shadow-md shadow-slate-300">TV</div>
                        <div><h1 className="font-black tracking-tight text-slate-800">TV WALL <span className="text-indigo-600">CONTROL</span></h1><p className="text-[10px] font-semibold uppercase tracking-[.24em] text-slate-400">Acesso operacional</p></div>
                    </div>
                    <Form action="/login" method="post" className="flex flex-col gap-4">
                        {({ errors, processing }) => (
                            <>
                                <label className="field-label">E-mail<input name="email" type="email" autoComplete="email" autoFocus className="field" placeholder="admin@example.com" /></label>
                                <label className="field-label">Senha<input name="password" type="password" autoComplete="current-password" className="field" /></label>
                                <label className="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="remember" value="1" className="size-4 rounded border-slate-300 bg-white accent-indigo-600" /> Manter sessão neste computador</label>
                                {Object.values(errors).map((error) => <p key={error} className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{error}</p>)}
                                <button className="primary-button mt-2" disabled={processing}>{processing ? 'Verificando…' : 'Entrar no painel'}</button>
                            </>
                        )}
                    </Form>
                </section>
            </main>
        </>
    );
}
