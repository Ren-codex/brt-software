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
        /**
         * Does this user hold an approver level anywhere?
         *
         * Gates the Approvals item in the sidebar. The page itself re-derives the
         * same answer server-side and shows only the sources the user can
         * approve, so this is purely about not advertising an empty screen.
         */
        canApproveAnything() {
            const permissions = this.$page?.props?.permissions || {};

            return Object.values(permissions).some(moduleGrants =>
                Object.values(moduleGrants || {}).some(levels =>
                    Array.isArray(levels) && (levels.includes('approver') || levels.includes('admin'))
                )
            );
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
