const demoProducts = [
    {
        name: 'Матрас Balance',
        image: '/images/demo/products/mattress-balance.webp',
    },
    {
        name: 'Топпер Cloud',
        image: '/images/demo/products/mattress-topper-cloud.webp',
    },
    {
        name: 'Комплект Gray',
        image: '/images/demo/products/bed-linen-gray.webp',
    },
    {
        name: 'Подушка Memory',
        image: '/images/demo/products/pillow-memory.webp',
    },
    {
        name: 'Плед Soft Touch',
        image: '/images/demo/products/throw-soft-touch.webp',
    },
    {
        name: 'Одеяло Air',
        image: '/images/demo/products/duvet-air.webp',
    },
    {
        name: 'Подушка Classic',
        image: '/images/demo/products/pillow-classic.webp',
    },
] as const;

export function DemoProductStrip() {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
            {demoProducts.map((product) => (
                <article
                    key={product.name}
                    className="min-w-0 rounded-lg border border-border-subtle bg-background p-2"
                >
                    <img
                        src={product.image}
                        alt={product.name}
                        className="aspect-square w-full rounded-md bg-surface-subtle object-cover"
                    />
                    <p className="mt-2 truncate text-xs font-medium">
                        {product.name}
                    </p>
                </article>
            ))}
        </div>
    );
}
