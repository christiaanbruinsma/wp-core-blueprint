import { setPropertyCommand } from '../../core/commands.js';
import { normalizeFlowHints } from './layout.js';

export const setFlowHintsCommand = (nodePath, hints) => (
	setPropertyCommand(nodePath, ['flow'], normalizeFlowHints(hints))
);
