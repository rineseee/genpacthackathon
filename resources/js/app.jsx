import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Header, TABS } from './radar/shell';
import YourBusiness from './radar/tabs/YourBusiness';
import AskExplore from './radar/tabs/AskExplore';
import AskWhy from './radar/tabs/AskWhy';
import DataSources from './radar/tabs/DataSources';

const VIEWS = {
    business: YourBusiness,
    explore: AskExplore,
    why: AskWhy,
    sources: DataSources,
};

const fromHash = () => {
    const id = window.location.hash.replace('#', '');
    return TABS.some((t) => t.id === id) ? id : 'business';
};

function App() {
    const [tab, setTab] = useState(fromHash);

    // The tab lives in the hash so a demo link can open straight on one tab.
    useEffect(() => {
        const onHash = () => setTab(fromHash());
        window.addEventListener('hashchange', onHash);

        return () => window.removeEventListener('hashchange', onHash);
    }, []);

    const go = (id) => {
        window.location.hash = id;
        setTab(id);
    };

    const View = VIEWS[tab] ?? YourBusiness;

    return (
        <>
            <Header tab={tab} onTab={go} />
            <main>
                <View />
            </main>
        </>
    );
}

const mount = document.getElementById('radar-root');

if (mount) {
    createRoot(mount).render(<App />);
}
