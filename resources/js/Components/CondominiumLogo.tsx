import { Building2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    src?: string | null;
    alt: string;
    className?: string;
    imageClassName?: string;
    fallbackClassName?: string;
    iconClassName?: string;
}

export default function CondominiumLogo({
    src,
    alt,
    className = 'h-12 w-12 shrink-0 rounded-lg',
    imageClassName = 'border border-gray-100 bg-white object-contain',
    fallbackClassName = 'bg-blue-100 text-blue-600',
    iconClassName = 'h-5 w-5',
}: Props) {
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        setFailed(false);
    }, [src]);

    if (src && !failed) {
        return (
            <img
                src={src}
                alt={alt}
                onError={() => setFailed(true)}
                className={`${className} ${imageClassName}`}
            />
        );
    }

    return (
        <div className={`flex items-center justify-center ${className} ${fallbackClassName}`}>
            <Building2 className={iconClassName} />
        </div>
    );
}
