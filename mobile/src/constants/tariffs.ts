export interface TariffScale{
    code: string;
    label: string;
    ratePerUnit: number; //zar
    unit: 'hour' | 'page' | 'appearance' | 'item'
}

//Placeholder scale - replace with the firm's real tariff table before go-live
export const TARIFF_SCALES: TariffScale[] = [
    { code: 'A1', label: 'Attorney consultation', ratePerUnit: 1850, unit: 'hour' },
    { code: 'A2', label: 'Court appearance', ratePerUnit: 3200, unit: 'appearance' },
    { code: 'A3', label: 'Drafting', ratePerUnit: 1200, unit: 'hour' },
    { code: 'A4', label: 'Travel time', ratePerUnit: 650, unit: 'hour' },
    { code: 'A5', label: 'Photocopying / printing', ratePerUnit: 2.5, unit: 'page' },
]