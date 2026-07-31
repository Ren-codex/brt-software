<template>
    <figure class="mf">
        <div class="mf-frame">
            <!-- A real screenshot, when one has been supplied -->
            <img v-if="src" class="mf-shot" :src="src" :alt="caption || 'System screenshot'" />

            <!-- Otherwise a schematic of the screen -->
            <svg v-else class="mf-art" viewBox="0 0 320 180" role="img" :aria-label="caption || art">
                <!-- ------------------------------------------------ login -->
                <g v-if="art === 'login'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="96" y="26" width="128" height="128" rx="6" fill="#fffdf8" stroke="#c3b49a" />
                    <circle cx="160" cy="54" r="13" fill="#2f6d4f" />
                    <path d="M154 54h12M160 48v12" stroke="#f6edda" stroke-width="2" stroke-linecap="round" />
                    <rect x="112" y="78" width="96" height="13" rx="3" fill="#efe7d6" stroke="#cdbfa6" />
                    <rect x="112" y="97" width="96" height="13" rx="3" fill="#efe7d6" stroke="#cdbfa6" />
                    <rect x="112" y="119" width="96" height="15" rx="4" fill="#2f6d4f" />
                    <text x="160" y="130" class="lbl-on" text-anchor="middle">Sign in</text>
                    <text x="116" y="87" class="lbl">username</text>
                    <text x="116" y="106" class="lbl">password</text>
                </g>

                <!-- ---------------------------------------------- sidebar -->
                <g v-else-if="art === 'sidebar'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="0" y="0" width="104" height="180" rx="4" fill="#1d4835" />
                    <rect x="14" y="14" width="52" height="9" rx="2" fill="#7fd4a8" />
                    <g fill="#3d6a55">
                        <rect x="12" y="38" width="80" height="13" rx="3" fill="#2f6d4f" />
                        <rect x="12" y="56" width="80" height="13" rx="3" />
                        <rect x="12" y="74" width="80" height="13" rx="3" />
                        <rect x="12" y="92" width="80" height="13" rx="3" />
                    </g>
                    <g fill="#28503c" opacity="0.55">
                        <rect x="12" y="110" width="80" height="13" rx="3" />
                        <rect x="12" y="128" width="80" height="13" rx="3" />
                    </g>
                    <text x="18" y="47" class="lbl-on">Dashboard</text>
                    <text x="18" y="65" class="lbl-on">Sales</text>
                    <text x="18" y="83" class="lbl-on">Inventory</text>
                    <text x="18" y="101" class="lbl-on">Payroll</text>
                    <path d="M104 118h22" stroke="#b06a3a" stroke-width="1.2" stroke-dasharray="3 2" />
                    <circle cx="132" cy="118" r="7" fill="#b06a3a" />
                    <text x="132" y="121" class="num" text-anchor="middle">1</text>
                    <text x="145" y="116" class="lbl">hidden for</text>
                    <text x="145" y="125" class="lbl">your role</text>
                    <rect x="120" y="18" width="186" height="76" rx="4" fill="#fffdf8" stroke="#c3b49a" />
                    <rect x="130" y="28" width="60" height="7" rx="2" fill="#ded2b8" />
                    <rect x="130" y="42" width="166" height="6" rx="2" fill="#ebe2cd" />
                    <rect x="130" y="54" width="140" height="6" rx="2" fill="#ebe2cd" />
                    <rect x="130" y="66" width="158" height="6" rx="2" fill="#ebe2cd" />
                    <rect x="130" y="78" width="110" height="6" rx="2" fill="#ebe2cd" />
                </g>

                <!-- -------------------------------------------- dashboard -->
                <g v-else-if="art === 'dashboard'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="12" y="10" width="150" height="20" rx="6" fill="#e4dbc6" />
                    <rect x="16" y="14" width="46" height="12" rx="4" fill="#fffdf8" />
                    <text x="39" y="23" class="lbl" text-anchor="middle">Sales</text>
                    <text x="86" y="23" class="lbl" text-anchor="middle">Inventory</text>
                    <text x="136" y="23" class="lbl" text-anchor="middle">Team</text>
                    <rect x="200" y="10" width="108" height="20" rx="6" fill="#fffdf8" stroke="#8fbfa4" />
                    <rect x="205" y="14" width="12" height="12" rx="3" fill="#2f6d4f" />
                    <text x="223" y="23" class="lbl">User Manual</text>
                    <g>
                        <rect x="12" y="40" width="70" height="38" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                        <rect x="88" y="40" width="70" height="38" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                        <rect x="164" y="40" width="70" height="38" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                        <rect x="240" y="40" width="68" height="38" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    </g>
                    <g fill="#ded2b8">
                        <rect x="20" y="48" width="30" height="5" rx="2" />
                        <rect x="96" y="48" width="30" height="5" rx="2" />
                        <rect x="172" y="48" width="30" height="5" rx="2" />
                        <rect x="248" y="48" width="30" height="5" rx="2" />
                    </g>
                    <g fill="#2f6d4f">
                        <rect x="20" y="58" width="42" height="9" rx="2" />
                        <rect x="96" y="58" width="34" height="9" rx="2" />
                        <rect x="172" y="58" width="38" height="9" rx="2" />
                        <rect x="248" y="58" width="30" height="9" rx="2" />
                    </g>
                    <rect x="12" y="88" width="196" height="80" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    <polyline points="24,150 56,132 88,140 120,112 152,122 184,100"
                        fill="none" stroke="#2f6d4f" stroke-width="2.2" stroke-linecap="round" />
                    <polygon points="24,150 56,132 88,140 120,112 152,122 184,100 184,158 24,158"
                        fill="#2f6d4f" opacity="0.13" />
                    <rect x="216" y="88" width="92" height="80" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    <circle cx="262" cy="128" r="26" fill="none" stroke="#e0d4bb" stroke-width="11" />
                    <path d="M262 102a26 26 0 0 1 22 39" fill="none" stroke="#c08a4a" stroke-width="11" />
                    <path d="M262 102a26 26 0 0 0-24 36" fill="none" stroke="#2f6d4f" stroke-width="11" />
                </g>

                <!-- ------------------------------------------ sales order -->
                <g v-else-if="art === 'sales-order'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="12" y="10" width="296" height="160" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    <rect x="12" y="10" width="296" height="22" rx="5" fill="#2f6d4f" />
                    <text x="24" y="25" class="lbl-on">New Sales Order</text>
                    <rect x="24" y="42" width="130" height="14" rx="3" fill="#efe7d6" stroke="#cdbfa6" />
                    <text x="30" y="52" class="lbl">Customer</text>
                    <circle cx="166" cy="49" r="7" fill="#b06a3a" />
                    <text x="166" y="52" class="num" text-anchor="middle">1</text>
                    <line x1="24" y1="68" x2="296" y2="68" stroke="#ded2b8" />
                    <g>
                        <rect x="24" y="74" width="272" height="14" rx="2" fill="#f6f1e4" />
                        <rect x="24" y="92" width="272" height="14" rx="2" fill="#faf6ec" />
                        <rect x="24" y="110" width="272" height="14" rx="2" fill="#f6f1e4" />
                    </g>
                    <g fill="#c9bda3">
                        <rect x="30" y="79" width="70" height="5" rx="2" />
                        <rect x="30" y="97" width="86" height="5" rx="2" />
                        <rect x="30" y="115" width="62" height="5" rx="2" />
                        <rect x="200" y="79" width="26" height="5" rx="2" />
                        <rect x="200" y="97" width="26" height="5" rx="2" />
                        <rect x="200" y="115" width="26" height="5" rx="2" />
                        <rect x="250" y="79" width="40" height="5" rx="2" />
                        <rect x="250" y="97" width="40" height="5" rx="2" />
                        <rect x="250" y="115" width="40" height="5" rx="2" />
                    </g>
                    <circle cx="308" cy="99" r="7" fill="#b06a3a" />
                    <text x="308" y="102" class="num" text-anchor="middle">2</text>
                    <line x1="24" y1="132" x2="296" y2="132" stroke="#ded2b8" />
                    <text x="200" y="146" class="lbl">Total</text>
                    <rect x="232" y="139" width="58" height="10" rx="2" fill="#2f6d4f" />
                    <rect x="24" y="150" width="56" height="14" rx="4" fill="#2f6d4f" />
                    <text x="52" y="160" class="lbl-on" text-anchor="middle">Save</text>
                </g>

                <!-- --------------------------------------- purchase order -->
                <g v-else-if="art === 'purchase-order'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="12" y="10" width="296" height="160" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    <rect x="12" y="10" width="296" height="22" rx="5" fill="#8a5a2b" />
                    <text x="24" y="25" class="lbl-on">Purchase Order</text>
                    <rect x="226" y="14" width="70" height="14" rx="3" fill="#a9743d" />
                    <text x="261" y="24" class="lbl-on" text-anchor="middle">PO-0042</text>
                    <circle cx="308" cy="21" r="7" fill="#b06a3a" />
                    <text x="308" y="24" class="num" text-anchor="middle">1</text>
                    <rect x="24" y="44" width="140" height="14" rx="3" fill="#efe7d6" stroke="#cdbfa6" />
                    <text x="30" y="54" class="lbl">Supplier</text>
                    <g>
                        <rect x="24" y="70" width="272" height="14" rx="2" fill="#f6f1e4" />
                        <rect x="24" y="88" width="272" height="14" rx="2" fill="#faf6ec" />
                        <rect x="24" y="106" width="272" height="14" rx="2" fill="#f6f1e4" />
                    </g>
                    <g fill="#c9bda3">
                        <rect x="30" y="75" width="88" height="5" rx="2" />
                        <rect x="30" y="93" width="72" height="5" rx="2" />
                        <rect x="30" y="111" width="94" height="5" rx="2" />
                        <rect x="248" y="75" width="40" height="5" rx="2" />
                        <rect x="248" y="93" width="40" height="5" rx="2" />
                        <rect x="248" y="111" width="40" height="5" rx="2" />
                    </g>
                    <rect x="24" y="134" width="52" height="14" rx="7" fill="#fdf0d8" stroke="#c08a4a" />
                    <text x="50" y="144" class="lbl" text-anchor="middle">Pending</text>
                    <path d="M80 141h18" stroke="#b9a888" stroke-width="1.2" marker-end="url(#mfArrow)" />
                    <rect x="102" y="134" width="56" height="14" rx="7" fill="#e6f4ec" stroke="#2f6d4f" />
                    <text x="130" y="144" class="lbl" text-anchor="middle">Approved</text>
                    <path d="M162 141h18" stroke="#b9a888" stroke-width="1.2" marker-end="url(#mfArrow)" />
                    <rect x="184" y="134" width="60" height="14" rx="7" fill="#e6f4ec" stroke="#2f6d4f" />
                    <text x="214" y="144" class="lbl" text-anchor="middle">Completed</text>
                </g>

                <!-- ---------------------------------------- low stock alert -->
                <g v-else-if="art === 'stock-alert'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <text x="14" y="20" class="lbl">Low Stock Alert</text>
                    <rect x="92" y="10" width="74" height="13" rx="6" fill="#fbe3e3" stroke="#d08a8a" />
                    <text x="129" y="20" class="lbl" text-anchor="middle">3 need action</text>
                    <g v-for="(row, i) in 3" :key="i">
                        <rect x="12" :y="34 + i * 46" width="296" height="38" rx="5" fill="#fffdf8"
                            stroke="#c3b49a" />
                        <rect x="12" :y="34 + i * 46" width="4" height="38" rx="2"
                            :fill="['#c0392b', '#c08a4a', '#8a7f6a'][i]" />
                        <rect x="26" :y="43 + i * 46" width="74" height="6" rx="2" fill="#cfc3aa" />
                        <rect x="26" :y="55 + i * 46" width="46" height="5" rx="2" fill="#e0d6c0" />
                        <rect x="26" :y="64 + i * 46" width="150" height="4" rx="2" fill="#e6dcc8" />
                        <rect x="26" :y="64 + i * 46" :width="[24, 58, 8][i]" height="4" rx="2"
                            :fill="['#c0392b', '#c08a4a', '#8a7f6a'][i]" />
                        <rect x="238" :y="44 + i * 46" width="58" height="14" rx="7"
                            :fill="['#fbe3e3', '#fdf0d8', '#eceae4'][i]"
                            :stroke="['#d08a8a', '#c08a4a', '#a9a294'][i]" />
                        <text x="267" :y="54 + i * 46" class="lbl" text-anchor="middle">
                            {{ ['Critical', 'Low', 'Out'][i] }}
                        </text>
                    </g>
                </g>

                <!-- ---------------------------------------------- payroll -->
                <g v-else-if="art === 'payroll'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="12" y="10" width="296" height="160" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    <rect x="12" y="10" width="296" height="22" rx="5" fill="#2f6d4f" />
                    <text x="24" y="25" class="lbl-on">Payroll — period 01–15</text>
                    <line x1="24" y1="44" x2="296" y2="44" stroke="#ded2b8" />
                    <text x="26" y="41" class="lbl">Employee</text>
                    <text x="170" y="41" class="lbl">Gross</text>
                    <text x="214" y="41" class="lbl">Deduct</text>
                    <text x="262" y="41" class="lbl">Net</text>
                    <g v-for="(row, i) in 4" :key="i">
                        <rect x="24" :y="50 + i * 20" width="272" height="16" rx="2"
                            :fill="i % 2 ? '#faf6ec' : '#f6f1e4'" />
                        <circle cx="34" :cy="58 + i * 20" r="5" fill="#dfd3ba" />
                        <rect x="44" :y="55 + i * 20" width="72" height="5" rx="2" fill="#cfc3aa" />
                        <rect x="170" :y="55 + i * 20" width="30" height="5" rx="2" fill="#ded2b8" />
                        <rect x="214" :y="55 + i * 20" width="30" height="5" rx="2" fill="#e3c9c9" />
                        <rect x="258" :y="55 + i * 20" width="34" height="5" rx="2" fill="#2f6d4f" />
                    </g>
                    <rect x="24" y="138" width="48" height="14" rx="7" fill="#eceae4" stroke="#a9a294" />
                    <text x="48" y="148" class="lbl" text-anchor="middle">Draft</text>
                    <path d="M76 145h16" stroke="#b9a888" stroke-width="1.2" marker-end="url(#mfArrow)" />
                    <rect x="96" y="138" width="62" height="14" rx="7" fill="#fdf0d8" stroke="#c08a4a" />
                    <text x="127" y="148" class="lbl" text-anchor="middle">Approval</text>
                    <path d="M162 145h16" stroke="#b9a888" stroke-width="1.2" marker-end="url(#mfArrow)" />
                    <rect x="182" y="138" width="66" height="14" rx="7" fill="#e6f4ec" stroke="#2f6d4f" />
                    <text x="215" y="148" class="lbl" text-anchor="middle">For Release</text>
                </g>

                <!-- --------------------------------------------- employee -->
                <g v-else-if="art === 'employee'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="12" y="10" width="296" height="160" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    <circle cx="56" cy="54" r="26" fill="#e4dbc6" />
                    <circle cx="56" cy="46" r="9" fill="#b9a888" />
                    <path d="M38 70a18 18 0 0 1 36 0z" fill="#b9a888" />
                    <rect x="96" y="34" width="110" height="9" rx="2" fill="#2f2a24" opacity="0.75" />
                    <rect x="96" y="49" width="72" height="6" rx="2" fill="#cfc3aa" />
                    <rect x="96" y="62" width="90" height="6" rx="2" fill="#e0d6c0" />
                    <line x1="28" y1="96" x2="292" y2="96" stroke="#ded2b8" />
                    <g>
                        <rect x="28" y="106" width="118" height="24" rx="4" fill="#f7f2e6" stroke="#ddd1b8" />
                        <rect x="174" y="106" width="118" height="24" rx="4" fill="#f7f2e6" stroke="#ddd1b8" />
                        <rect x="28" y="136" width="118" height="24" rx="4" fill="#f7f2e6" stroke="#ddd1b8" />
                        <rect x="174" y="136" width="118" height="24" rx="4" fill="#f7f2e6" stroke="#ddd1b8" />
                    </g>
                    <text x="36" y="116" class="lbl">Position</text>
                    <text x="182" y="116" class="lbl">Salary</text>
                    <text x="36" y="146" class="lbl">Location</text>
                    <text x="182" y="146" class="lbl">Linked account</text>
                    <rect x="36" y="120" width="60" height="6" rx="2" fill="#cfc3aa" />
                    <rect x="182" y="120" width="46" height="6" rx="2" fill="#cfc3aa" />
                    <rect x="36" y="150" width="52" height="6" rx="2" fill="#cfc3aa" />
                    <rect x="182" y="150" width="64" height="6" rx="2" fill="#2f6d4f" />
                    <circle cx="300" cy="118" r="7" fill="#b06a3a" />
                    <text x="300" y="121" class="num" text-anchor="middle">1</text>
                </g>

                <!-- ------------------------------------------- remittance -->
                <g v-else-if="art === 'remittance'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <rect x="26" y="16" width="150" height="148" rx="5" fill="#fffdf8" stroke="#c3b49a" />
                    <rect x="26" y="16" width="150" height="20" rx="5" fill="#2f6d4f" />
                    <text x="38" y="30" class="lbl-on">Remittance</text>
                    <rect x="40" y="48" width="76" height="6" rx="2" fill="#cfc3aa" />
                    <rect x="40" y="60" width="52" height="5" rx="2" fill="#e0d6c0" />
                    <line x1="40" y1="76" x2="162" y2="76" stroke="#ded2b8" />
                    <g fill="#e6dcc8">
                        <rect x="40" y="84" width="122" height="6" rx="2" />
                        <rect x="40" y="96" width="122" height="6" rx="2" />
                        <rect x="40" y="108" width="90" height="6" rx="2" />
                    </g>
                    <text x="40" y="134" class="lbl">Amount</text>
                    <rect x="86" y="126" width="76" height="14" rx="3" fill="#2f6d4f" />
                    <path d="M186 90h26" stroke="#b9a888" stroke-width="1.4" marker-end="url(#mfArrow)" />
                    <g transform="rotate(-11 262 84)">
                        <rect x="216" y="58" width="92" height="52" rx="5" fill="none" stroke="#2f6d4f"
                            stroke-width="2.5" />
                        <text x="262" y="82" class="stamp" text-anchor="middle">APPROVED</text>
                        <text x="262" y="97" class="lbl" text-anchor="middle">Administrator</text>
                    </g>
                    <circle cx="200" cy="126" r="7" fill="#b06a3a" />
                    <text x="200" y="129" class="num" text-anchor="middle">1</text>
                    <text x="212" y="129" class="lbl">not cleared until approved</text>
                </g>

                <!-- -------------------------------------------- libraries -->
                <g v-else-if="art === 'libraries'">
                    <rect x="0" y="0" width="320" height="180" rx="4" fill="#f0ead9" />
                    <g v-for="(name, i) in libraryNames" :key="name">
                        <rect :x="14 + (i % 4) * 76" :y="18 + Math.floor(i / 4) * 54" width="68" height="44"
                            rx="5" fill="#fffdf8" stroke="#c3b49a" />
                        <rect :x="24 + (i % 4) * 76" :y="28 + Math.floor(i / 4) * 54" width="16" height="16"
                            rx="3" fill="#e4dbc6" />
                        <text :x="48 + (i % 4) * 76" :y="56 + Math.floor(i / 4) * 54" class="lbl"
                            text-anchor="middle">{{ name }}</text>
                    </g>
                    <path d="M14 148h292" stroke="#ded2b8" stroke-dasharray="3 3" />
                    <text x="160" y="166" class="lbl" text-anchor="middle">
                        feeds every dropdown in the system
                    </text>
                </g>

                <!-- shared arrowhead -->
                <defs>
                    <marker id="mfArrow" viewBox="0 0 8 8" refX="6" refY="4" markerWidth="5"
                        markerHeight="5" orient="auto">
                        <path d="M0 1l6 3-6 3z" fill="#b9a888" />
                    </marker>
                </defs>
            </svg>
        </div>

        <figcaption v-if="caption" class="mf-caption">
            <span v-if="callouts.length" class="mf-callouts">
                <span v-for="(note, i) in callouts" :key="i" class="mf-callout">
                    <span class="mf-callout-num">{{ i + 1 }}</span>{{ note }}
                </span>
            </span>
            {{ caption }}
        </figcaption>
    </figure>
</template>

<script>
export default {
    name: 'ManualFigure',
    props: {
        /** Name of a built-in schematic (see the v-if chain above). */
        art: { type: String, default: '' },
        /**
         * Path to a real screenshot, e.g. '/images/manual/dashboard.png'.
         * When set it replaces the schematic entirely.
         */
        src: { type: String, default: '' },
        caption: { type: String, default: '' },
        /** Optional notes matching the numbered markers drawn on the schematic. */
        callouts: { type: Array, default: () => [] },
    },
    data() {
        return {
            libraryNames: ['Products', 'Brands', 'Units', 'Locations', 'Statuses', 'Suppliers', 'Positions', 'Roles'],
        };
    },
};
</script>

<style scoped>
.mf {
    margin: 0 0 1rem;
}

.mf-frame {
    border: 1px solid rgba(120, 96, 66, 0.3);
    border-radius: 6px;
    background: #f0ead9;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(60, 40, 20, 0.12);
}

.mf-art,
.mf-shot {
    display: block;
    width: 100%;
    height: auto;
}

.mf-caption {
    margin-top: 0.4rem;
    font-size: 0.82em;
    line-height: 1.45;
    color: #7d6f5c;
}

.mf-callouts {
    display: block;
    margin-bottom: 0.15rem;
}

.mf-callout {
    display: inline-flex;
    align-items: center;
    gap: 0.3em;
    margin-right: 0.7em;
    color: #8a5a2b;
}

.mf-callout-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25em;
    height: 1.25em;
    border-radius: 50%;
    background: #b06a3a;
    color: #fff;
    font-size: 0.78em;
    font-weight: 700;
}

/* SVG label styles */
.mf-art :deep(.lbl) {
    font-size: 7px;
    fill: #6f6350;
    font-family: inherit;
}

.mf-art :deep(.lbl-on) {
    font-size: 7px;
    fill: #f4ecd9;
    font-family: inherit;
}

.mf-art :deep(.num) {
    font-size: 7px;
    font-weight: 700;
    fill: #fff;
    font-family: inherit;
}

.mf-art :deep(.stamp) {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    fill: #2f6d4f;
    font-family: inherit;
}
</style>
