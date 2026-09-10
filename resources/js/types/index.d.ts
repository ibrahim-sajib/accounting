export interface PageProps {
auth: {
            user: {
                id: number;
                name: string;
                email: string;
                email_verified_at: string | null;
                phone: string | null;
                is_super_admin: boolean;
                status: string;
            };
            permissions: string[];
        };
    current_company: {
        id: number;
        name: string;
        legal_name: string | null;
        currency_code: string | null;
        country_code: string | null;
        status: string;
        accounting_basis: string;
        logo: string | null;
    } | null;
    companies: {
        id: number;
        name: string;
        status: string;
        currency_code: string | null;
    }[];
    flash: {
        success?: string;
        error?: string;
    };
}