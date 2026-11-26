import { AbsoluteFill } from 'remotion';
import { C, DeviceFrame, fontCss, gradText, Mark } from './Film';
import { reel } from './timeline';

// The YouTube thumbnail: the candidate's finished timeline in front of the employer's applicant page.
export function Thumb() {
    const employer = reel('review/hannah', 0, [['end', 9, 'hold']]);
    const candidate = reel('review/rafi', 0, [['end', 9, 'hold']]);
    return (
        <AbsoluteFill style={{ background: C.night, overflow: 'hidden' }}>
            <style>{fontCss}</style>
            <div style={{ position: 'absolute', left: 980, top: 150, transform: 'rotate(-3deg)', transformOrigin: '0 0', opacity: 0.55 }}>
                <DeviceFrame d={{ kind: 'browser', reel: employer, x: 0, y: 0, s: 0.86 }} t={1} dark />
            </div>
            <div style={{ position: 'absolute', left: 1240, top: 70, transform: 'rotate(4deg)', transformOrigin: '0 0' }}>
                <DeviceFrame d={{ kind: 'phone', reel: candidate, x: 0, y: 0, s: 1.32 }} t={1} dark />
            </div>
            <AbsoluteFill style={{ background: 'linear-gradient(90deg, #0B0B0D 0%, #0B0B0D 46%, rgba(11,11,13,0) 64%)' }} />
            <div style={{ position: 'absolute', left: 96, top: 110, display: 'flex', alignItems: 'center', gap: 22 }}>
                <Mark size={84} />
                <div style={{ fontFamily: 'Geist', fontWeight: 700, fontSize: 80, letterSpacing: '-0.04em', color: C.nightInk }}>HearBack</div>
            </div>
            <div style={{ position: 'absolute', left: 96, top: 268, width: 1100, fontFamily: 'Geist', fontWeight: 800, fontSize: 158, lineHeight: 0.98, letterSpacing: '-0.05em', color: C.nightInk }}>
                Job board<br />where <span style={gradText(true)}>silence<br />shows</span>
            </div>
            <div style={{ position: 'absolute', left: 100, top: 902, fontFamily: 'Geist Mono', fontWeight: 500, fontSize: 38, letterSpacing: '0.06em', color: C.nightMuted }}>LARAVEL 13 · LIVEWIRE 4 · FILAMENT 5</div>
        </AbsoluteFill>
    );
}
