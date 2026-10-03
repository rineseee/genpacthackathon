import { createRoot } from 'react-dom/client';
import Radar from './radar/Radar';

const mount = document.getElementById('radar-root');

if (mount) {
    createRoot(mount).render(<Radar companyId={Number(mount.dataset.companyId) || 1} />);
}
