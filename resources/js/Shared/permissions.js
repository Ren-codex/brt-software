function hasLevel(levels, level) {
    return Array.isArray(levels) && (levels.includes(level) || levels.includes('admin'));
}

/**
 * this.can('sales', 'view')                     -> module-wide check
 * this.can('sales', 'sales_orders', 'encoder')   -> submodule-specific check
 * A module-wide grant always satisfies a submodule-specific check for that module.
 */
export const permissionsMixin = {
    methods: {
        can(moduleKey, submoduleKeyOrLevel, level) {
            let submoduleKey = submoduleKeyOrLevel;
            let requiredLevel = level;
            if (typeof level === 'undefined') {
                submoduleKey = null;
                requiredLevel = submoduleKeyOrLevel;
            }

            const permissions = this.$page?.props?.permissions || {};
            const moduleGrants = permissions[moduleKey];
            if (!moduleGrants) {
                return false;
            }

            if (submoduleKey && hasLevel(moduleGrants[submoduleKey], requiredLevel)) {
                return true;
            }

            return hasLevel(moduleGrants._module, requiredLevel);
        },
        canAny(moduleKey, submoduleKey) {
            const permissions = this.$page?.props?.permissions || {};
            const moduleGrants = permissions[moduleKey];
            if (!moduleGrants) {
                return false;
            }

            const levels = submoduleKey
                ? (moduleGrants[submoduleKey] || [])
                : Object.values(moduleGrants).flat();

            const moduleWide = moduleGrants._module || [];

            return levels.length > 0 || moduleWide.length > 0;
        },
    },
};
