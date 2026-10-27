/**
 * Ang Tagalog na bersyon ng guide book.
 *
 * Same shape as the English text in moduleGuides.js and beginnerNotes.js, keyed
 * by module. Only the words are here — the structure (which chapters exist,
 * their icons, the status behind each stage) stays in the English files, and
 * guidesFor('tl') lays this text over it. A chapter or field missing here
 * simply falls back to English rather than breaking the book.
 *
 * Two deliberate choices in the wording:
 *
 *  - Names that appear on the screen stay in English: status labels (For
 *    Payment, Closed), buttons (Add Payroll, Submit for Approval) and menu
 *    items. A reader has to be able to match the guide to what they see.
 *
 *  - The Tagalog is the everyday office kind, with the usual English loanwords
 *    (order, payroll, i-save), not formal textbook Filipino. It is meant to be
 *    understood on the first read by someone new.
 *
 * Kapag binago ang English, baguhin din dito.
 */

/** The book's own labels: buttons, headings and page titles. */
export const UI = {
    en: {
        bookTitle: 'Guide Book',
        bookSubtitle: 'Learn the system, one page at a time',
        chapter: 'Chapter',
        back: 'Back',
        next: 'Next',
        nextChapter: 'Next chapter',
        theEnd: 'The end',
        pageOf: (page, total) => `Page ${page} of ${total} in this chapter`,
        contents: 'Contents',
        fullScreen: 'Full screen',
        exitFullScreen: 'Exit full screen',
        close: 'Close',
        switchTo: 'Tagalog',
        inOneMinute: 'In one minute',
        youAreHere: 'You are here',
        nextStep: 'Next:',
        thatsYou: "That's you",
        yourPart: 'Your part in this',
        yourPartLead: 'With your role, this is what you can do here:',
        yourPartNone: 'Your role does not include this part of the system, so you will not see it in your menu. You can still read about it here.',
        wholeModule: 'Everywhere in this module',
        myChapters: 'Showing the chapters for your role',
        allChapters: 'Showing every chapter',
        showAll: 'Show all chapters',
        showMine: 'Only my chapters',
        notYours: 'Not in your role',
        loadingRoles: 'Loading roles…',
        rolesError: 'Could not load the roles for this chapter.',
        noRoles: 'Nobody has been given access to this yet.',
        superAdmin: ['A ', 'Super Admin', ' can do everything here. Roles are changed under Libraries → Roles.'],
        pages: {
            start: 'Start here',
            example: 'An example',
            who: 'Who does what',
            whoLead: 'Each role has its own part to play here. If you need something done that you cannot do yourself, this page tells you who to ask.',
            stages: 'The stages',
            steps: 'Step by step',
            notes: 'Good to know',
            words: 'Words you will see',
        },
    },
    tl: {
        bookTitle: 'Gabay na Aklat',
        bookSubtitle: 'Aralin ang sistema, isang pahina sa bawat pagkakataon',
        chapter: 'Kabanata',
        back: 'Bumalik',
        next: 'Susunod',
        nextChapter: 'Susunod na kabanata',
        theEnd: 'Wakas',
        pageOf: (page, total) => `Pahina ${page} ng ${total} sa kabanatang ito`,
        contents: 'Nilalaman',
        fullScreen: 'Buong screen',
        exitFullScreen: 'Lumabas sa buong screen',
        close: 'Isara',
        switchTo: 'English',
        inOneMinute: 'Sa loob ng isang minuto',
        youAreHere: 'Nandito ka',
        nextStep: 'Susunod:',
        thatsYou: 'Ikaw ito',
        yourPart: 'Ang bahagi mo rito',
        yourPartLead: 'Sa role mo, ito ang kaya mong gawin dito:',
        yourPartNone: 'Hindi kasama sa role mo ang bahaging ito ng sistema, kaya hindi mo ito makikita sa iyong menu. Puwede mo pa rin itong basahin dito.',
        wholeModule: 'Sa buong module na ito',
        myChapters: 'Ipinapakita ang mga kabanata para sa role mo',
        allChapters: 'Ipinapakita ang lahat ng kabanata',
        showAll: 'Ipakita lahat ng kabanata',
        showMine: 'Mga kabanata ko lang',
        notYours: 'Wala sa role mo',
        loadingRoles: 'Kinukuha ang mga role…',
        rolesError: 'Hindi makuha ang mga role para sa kabanatang ito.',
        noRoles: 'Wala pang nabibigyan ng access dito.',
        superAdmin: ['Ang ', 'Super Admin', ' ay kayang gawin ang lahat dito. Binabago ang mga role sa Libraries → Roles.'],
        pages: {
            start: 'Magsimula rito',
            example: 'Isang halimbawa',
            who: 'Sino ang gumagawa ng ano',
            whoLead: 'May kanya-kanyang gawain ang bawat role dito. Kung may kailangan kang ipagawa na hindi mo kaya, makikita mo rito kung kanino lalapit.',
            stages: 'Ang mga yugto',
            steps: 'Hakbang-hakbang',
            notes: 'Mabuting malaman',
            words: 'Mga salitang makikita mo',
        },
    },
};

/** What each access level lets a role do. */
export const LEVELS_TL = {
    admin: { label: 'Buong kontrol', hint: 'Lahat sa bahaging ito, pati pagbura' },
    approver: { label: 'Nag-aapruba', hint: 'Nag-aapruba o tumatanggi sa ipinasa ng iba' },
    releaser: { label: 'Nagre-release', hint: 'Naglalabas o nagbabayad ng naaprubahan na' },
    encoder: { label: 'Gumagawa at nag-e-edit', hint: 'Naglalagay ng bagong record at nag-e-edit nito' },
    void: { label: 'Nagbo-void', hint: 'Nagkakansela o nagbo-void ng record' },
    view: { label: 'Tumitingin', hint: 'Nakakabukas at nakakabasa, pero hindi nakakapagbago' },
};

export const GUIDES_TL = {
    sales: {
        title: 'Sales',
        summary: 'Pagbebenta sa mga customer: pagkuha ng order, paghahatid nito, paniningil ng bayad, at pagpasok ng perang iyon sa opisina.',
        stagesTitle: 'Mga yugto ng isang sales order',
        stages: [
            { what: 'Nailagay na ang order at wala pa, o bahagi pa lang, ang nasisingil.' },
            { what: 'May bahagi nang nasingil; may natitira pang balanse.' },
            { what: 'Bayad na nang buo, pero hindi pa naihahatid o naibibigay ang produkto.' },
            { what: 'Naihatid at bayad na, pero nasa driver o checker pa ang pera.' },
            { what: 'Bayad na, naihatid na, at nasa opisina na ang pera. Wala nang gagawin.' },
            { what: 'Kinansela ang order, o ibinalik ng customer ang produkto.' },
        ],
        steps: [
            'Gumawa ng order: piliin ang customer, ang sales rep, ang mga item at ang paraan ng bayad (Cash, COD o Credit).',
            'Kung ihahatid, maglagay ng Driver. Maglagay ng Checker kung may kasama — ang checker ang tumatanggap ng bayad ng customer.',
            'Markahan ang order na naihatid sa Deliveries board. Doon din itinatala ang perang nasingil sa pinto.',
            'Ibinibigay ng driver o checker ang pera sa sales rep, at itinatala ang pag-abot na iyon.',
            'Nire-remit ng sales rep ang mga nasingil; tinitingnan ng approver kung tugma ang remittance sa natanggap.',
        ],
        notes: [
            'Ang Cash ay binabayaran sa counter. Ang COD ay sinisingil pagdating ng produkto. Ang Credit ay babayaran sa susunod, bago ang due date, at kasama sa credit limit ng customer.',
            'Gamitin ang timeline button sa isang sales order para makita kung nasaang yugto na ito at kung ano ang hinihintay.',
            'Ang discount ay kada unit, mula ₱0 hanggang ₱100.',
            'Ang sales return ay dapat gawin sa loob ng itinakdang bilang ng araw mula sa resibo, at dumadaan sa approval.',
            'Ang sales rep ay sariling order lang ang nakikita; ang sales administrator ay nakikita ang lahat.',
        ],
    },
    inventory: {
        title: 'Inventory',
        summary: 'Pagbili ng stock sa mga supplier, pagtanggap nito sa bodega, at pagbabantay kung ilan ang natitira.',
        stagesTitle: 'Mula request hanggang stock',
        stages: [
            { what: 'May humiling na bumili ng stock. Naghihintay ito ng approver.' },
            { what: 'Nagiging Purchase Order ang request at nabibigyan ng PO number.' },
            { what: 'Dumating ang produkto at tinatanggap laban sa PO, buo man o bahagi. Bawat dating ay nagiging isang batch ng stock.' },
            { what: 'Natanggap na ang lahat ng nasa PO.' },
            { what: 'Hindi inaprubahan ang request, o na-void ang PO.' },
        ],
        steps: [
            'Gumawa ng purchase request: piliin ang supplier, ang mga produkto at ang dami.',
            'Inaaprubahan ito ng approver, kaya nagiging Purchase Order na may PO number.',
            'Pagdating ng produkto, i-receive ito laban sa PO. Ilagay ang batch, ang dami at ang mga presyo.',
            'Ang natanggap na dami ay nadaragdag sa Inventory Stocks at puwede nang ibenta.',
            'Kung may sira o kulang, gumawa ng Stock Return laban sa PO. Kapag naaprubahan, itala kung ano ang pinalitan ng supplier at kung ano ang lugi.',
        ],
        notes: [
            'May minimum stock ang bawat produkto. Kapag bumaba rito ang stock, may low-stock notification ang mga administrator.',
            'Naka-batch ang stock, kaya ang parehong produkto ay puwedeng may magkaibang puhunan at presyo.',
            'Ang mga purchase request at stock return na naghihintay ng desisyon ay makikita rin sa Approvals page.',
        ],
    },
    payroll: {
        title: 'Payroll',
        summary: 'Pagpapasahod sa mga empleyado: paghahanda ng payroll para sa isang grupo, pagpapa-aprubahan nito, pag-release, at pamamahala ng mga loan ng empleyado.',
        stagesTitle: 'Mga yugto ng isang payroll',
        stages: [
            { what: 'Inihahanda pa. Malaya itong baguhin o burahin ng gumawa nito.', next: 'Tapusin ang mga entry, saka gamitin ang Submit for Approval.' },
            { what: 'Naipasa na at naghihintay na suriin ng approver. Makikita rin ito sa Approvals page.', next: 'Bubuksan ito ng approver at pipili ng Approve o Disapprove.' },
            { what: 'Aprubado na. Napagkasunduan na ang mga halaga at handa nang ibayad.', next: 'Kapag ilalabas na ang sahod, gamitin ang Release Payroll.' },
            { what: 'Na-release na. Naitala na ang journal entry, naibawas na ang mga loan, at naka-lock na ito.', next: 'Wala nang gagawin. Puwede pa rin itong i-print pati ang mga payslip.' },
        ],
        stagesNote: 'Ang Disapproved ay labasan mula sa For Approval. Maibabalik ito ng approver sa Draft para maitama at maipasa ulit.',
        steps: [
            'Gumawa ng Payroll Group: ang mga empleyadong sabay-sabay pinapasahod.',
            'Mag-Add Payroll, piliin ang grupo at ang pay period, saka tingnan ang araw, earnings at deductions ng bawat empleyado.',
            'I-save bilang Draft, o i-Submit for Approval kapag handa na.',
            'Inaaprubahan ito ng approver; kapag lalabas na ang sahod, nire-release ito.',
            'I-print ang payroll report, ang mga payslip, o ang buong-pahinang pay statement ng isang empleyado.',
        ],
        notes: [
            'Net pay = (daily rate × araw na ipinasok) + iba pang earnings − deductions. Ang daily rate ay galing sa position ng empleyado.',
            'Ang loan ng empleyado ay dumadaan sa Pending → Approved → Active (na-release) → Completed, at nababayaran sa pamamagitan ng bawas sa payroll o direktang bayad.',
            'Ang mga payroll na naghihintay ng approval ay makikita rin sa Approvals page.',
        ],
        extraPages: [
            {
                title: 'Paano kinukuwenta ang sahod',
                blocks: [
                    { type: 'lead', text: 'Pareho ang paraan ng pagbuo ng sahod ng bawat empleyado sa payroll, mula sa tatlong simpleng kuwenta.' },
                    { type: 'formula', rows: [
                        ['Basic pay', 'daily rate × araw na ipinasok'],
                        ['Total earnings', 'basic pay + overtime at iba pang earnings'],
                        ['Net pay', 'total earnings − total deductions'],
                    ] },
                    { type: 'tip', text: 'Ang daily rate ay galing sa position ng empleyado. Binabago ito sa Libraries → Positions.' },
                    { type: 'tip', text: 'Isang hindi pa tapos na payroll lang ang puwede sa isang empleyado sa isang period. Kapag sinabi ng sistema na may kasalukuyang payroll na, i-release o alisin muna iyon.' },
                ],
            },
            {
                title: 'Pag-edit at pag-release',
                blocks: [
                    { type: 'lead', text: 'Kung sino ang puwedeng magbago ng payroll ay depende sa kung gaano na ito kalayo.' },
                    { type: 'table', head: ['Yugto', 'Sino ang puwedeng mag-edit'], rows: [
                        ['Draft', 'Ang gumawa nito'],
                        ['For Approval, For Release, Disapproved', 'Super Admin lang'],
                        ['Completed (na-release)', 'Wala na — naitala na sa libro'],
                    ] },
                    { type: 'heading', text: 'Ano ang nangyayari sa pag-release' },
                    { type: 'text', text: 'Ang pag-release ay wala nang bawian. Minamarkahan nitong Completed ang payroll, itinatala ang journal entry nito sa libro, at ibinabawas sa loan ng bawat empleyado ang binayaran dito. Kaya hindi na ma-e-edit ang payroll na na-release. Suriin muna ang mga halaga bago mag-release.' },
                ],
            },
            {
                title: 'Pag-print',
                blocks: [
                    { type: 'lead', text: 'May apat na puwedeng i-print mula sa isang payroll. Bawat isa ay bumubukas sa bagong tab, kung saan puwede itong basahin, i-save o i-print.' },
                    { type: 'icons', items: [
                        { title: 'Print', text: 'Ang payroll report: lahat ng empleyado sa isang pahina, may kabuuan.' },
                        { title: 'Payslips', text: 'Maliit na slip para sa bawat empleyado, dalawa sa isang pahina, para gupitin at ipamigay.' },
                        { title: 'Payslip (berdeng button sa row)', text: 'Ang slip ng isang empleyadong iyon.' },
                        { title: 'Pay statement (asul na button sa row)', text: 'Buong pahina para sa isang empleyado na nagpapakita kung paano kinuwenta ang bawat halaga.' },
                    ] },
                ],
            },
        ],
    },
    employees: {
        title: 'Employees',
        summary: 'Ang talaan ng lahat ng nagtatrabaho rito: ang kanilang detalye, position at status.',
        steps: [
            'Magdagdag ng empleyado kasama ang personal na detalye at ang kanyang Position.',
            'Ang position ang nagtatakda ng daily rate na ginagamit ng payroll, kaya piliin ito nang maingat.',
            'Markahan ang empleyado na inactive kapag umalis na, sa halip na burahin ang record.',
            'Para makapag-sign in ang empleyado, gawan siya ng user account sa Users.',
        ],
        notes: [
            'Ang mga driver ay pinipili sa sales order ayon sa position: sinumang may "driver" sa pangalan ng position.',
            'Ganoon din ang mga checker, ayon sa Checker position. Kadalasan ay inilalagay ito sa "Also works as", kaya hindi nagbabago ang sahod nila.',
            'Makikita sa profile ng empleyado ang kanyang mga loan at balanse.',
        ],
    },
    customers: {
        title: 'Customers',
        summary: 'Ang mga tao at negosyong binebentahan ninyo, kasama ang kanilang contact, credit limit at katayuan.',
        steps: [
            'Mag-search muna bago magdagdag, para hindi madoble ang parehong customer.',
            'Idagdag ang customer kasama ang pangalan, address at contact.',
            'Maglagay ng credit limit kung pinapayagan silang umutang.',
            'Buksan ang customer para makita ang kanyang mga order at kasaysayan ng pagbili.',
        ],
        notes: [
            'Ang credit na order ay tinitingnan laban sa credit limit ng customer kapag ginawa at kapag in-edit.',
            'Markahan ang customer na inactive sa halip na burahin; nananatili ang mga nakaraang order.',
            'Ang customer na blacklisted ay may babala para alam ng staff bago magbenta sa kanya.',
        ],
    },
    accounting: {
        title: 'Accounting',
        summary: 'Ang mga libro: journal entries, gastos, petty cash, bangko at cash, at ang mga financial report na nabubuo mula rito.',
        stagesTitle: 'Mga yugto ng isang gastos',
        stages: [
            { what: 'Naitala na at naghihintay ng approver.' },
            { what: 'Tiningnan laban sa hawak na cash o balanse sa bangko, saka itinala sa libro bilang journal entry.' },
            { what: 'Kinansela. Kung naaprubahan na ito, binabaligtad ang journal entry nito.' },
        ],
        steps: [
            'Karamihan ng journal entry ay kusang nagagawa — mula sa sales, resibo, payroll at gastos. Mano-manong maglagay lang kung may adjustment.',
            'Itala ang gastos kasama ang account, halaga at kung paano ito binayaran. Inaaprubahan ito ng approver.',
            'Sa petty cash, gumastos mula sa isang fund, saka magpasa ng replenishment request. Sinasabi ng approver kung galing sa cash o sa bangko ang pandagdag.',
            'Gamitin ang mga report (Trial Balance, Profit & Loss, Balance Sheet, Cash Flow) para suriin ang isang period.',
        ],
        notes: [
            'Hindi maaaprubahan ang gastos na mas malaki kaysa sa hawak na cash o sa balanse ng bangkong pagkukunan.',
            'Itinatama ang entry sa pamamagitan ng pagbaligtad nito, hindi pagbura, para buo ang talaan.',
            'Ang mga gastos at replenishment na naghihintay ng desisyon ay makikita rin sa Approvals page.',
        ],
    },
    user_management: {
        title: 'Users',
        summary: 'Kung sino ang puwedeng mag-sign in, at ano ang pinapayagang gawin ng bawat isa.',
        steps: [
            'Gumawa ng user account para sa empleyadong kailangang mag-sign in.',
            'Bigyan ang account ng isa o higit pang Role. Puwedeng magkaroon ng ilang role ang isang tao.',
            'Ang kayang gawin ng bawat role ay itinatakda sa Libraries → Roles, kada module at kada bahagi.',
            'I-deactivate ang account kapag umalis na ang tao, at i-reset ang password kapag hindi siya makapasok.',
        ],
        notes: [
            'Ang role ang nagpapasya ng access. Ang position ang nagpapasya ng sahod. Magkaiba ang dalawa.',
            'Ang mga driver at checker ay itinatakda sa record ng empleyado, ayon sa position, hindi ayon sa role.',
            'Kayang gawin ng Super Admin ang lahat, anuman ang nakalagay sa mga role.',
        ],
    },
    libraries: {
        title: 'Libraries',
        summary: 'Ang mga listahang pinagpipilian ng buong sistema: produkto, brand, unit, packaging, lokasyon, position, role at status.',
        steps: [
            'Unahin ang mga brand, unit at packaging; mula sa mga ito binubuo ang produkto.',
            'Idagdag ang mga produkto kasama ang timbang at minimum stock.',
            'Idagdag ang mga position kasama ang daily rate; dito inilalagay ang mga empleyado.',
            'Itakda kung ano ang kayang gawin ng bawat role sa Roles → Permissions.',
        ],
        notes: [
            'Ang pagbabago sa isang library entry ay tumatama sa lahat ng record na gumagamit nito, kaya mag-ingat sa pagpapalit ng pangalan.',
            'I-deactivate ang entry sa halip na burahin kapag hindi na ginagamit.',
        ],
    },
    approvals: {
        title: 'Approvals',
        summary: 'Iisang lugar para sa lahat ng naghihintay ng iyong desisyon, mula sa lahat ng module.',
        steps: [
            'Ang nasa listahan ay iyon lang mga pinapayagan kang aprubahan.',
            'Gamitin ang mga pill para mag-filter ayon sa uri, ang search box para maghanap, at ang Sort by para ayusin ang pagkakasunod.',
            'Mag-Approve o Reject mula mismo sa row, may kasamang remark kung gusto.',
            'Ang mga row na may Review button ay nangangailangan ng dagdag na impormasyon — buksan sa sariling module para matapos.',
        ],
        notes: [
            'Ang remittance at replenishment ay tinatapos sa sarili nilang screen, dahil kailangan ng detalye kung paano natanggap ang pera o saan ito manggagaling.',
            'Ang tseke ay naghihintay rito mula sa araw na natanggap o inilabas ito hanggang may magkumpirmang na-clear na ito ng bangko. Ang pag-approve ay pagkumpirmang na-clear; ang tumalbog na tseke ay itinatala sa Check Register, kung saan kailangan ng dahilan at ng supervisor.',
            'Kapag iba ang umaksyon sa isang request, nawawala ito sa iyong listahan at minamarkahang tapos ang iyong notification.',
            'Kung sino ang puwedeng mag-apruba ng bawat uri ay ayon sa "Nag-aapruba" na access sa module na iyon — buksan ang gabay ng module para makita ang mga role.',
        ],
    },
    dashboard: {
        title: 'Pag-ikot sa sistema',
        summary: 'Kung paano nakaayos ang sistema at saan makakahanap ng tulong.',
        steps: [
            'Nasa menu sa kaliwa ang mga module na may access ka. Puwedeng mas maikli ang sa iyo kaysa sa katrabaho mo.',
            'Ipinapakita ng bell sa itaas ang iyong mga notification; i-click ang isa para dumiretso sa tinutukoy nito.',
            'Sa mga list page, gamitin ang search box at ang Sort by para makahanap ng record.',
            'Buksan ang Guide button sa kahit anong page para mabasa ang tungkol sa module na kinaroroonan mo.',
        ],
        notes: [
            'Kung may nawawalang button o page, hindi ito kasama sa role mo. Magtanong sa administrator.',
            'Ang buong user manual ay nasa Dashboard.',
        ],
    },
};

export const BEGINNER_TL = {
    sales: {
        inShort: [
            'Dito mo itinatala ang binibili ng customer.',
            'Tapos ang order kapag tatlong bagay ang totoo: bayad na, naihatid na, at nasa opisina na ang pera.',
            'Binabantayan ng sistema kung alin sa tatlong iyon ang kulang pa.',
        ],
        example: {
            title: 'Umorder si Aling Rosa ng 10 sako, cash on delivery',
            steps: [
                'Gagawa ka ng sales order para kay Aling Rosa: 10 sako, COD ang bayad. Pipiliin mo ang driver, at ang checker na kasama niya.',
                'For Payment ang nakalagay sa order — wala pang nasisingil.',
                'Ihahatid ng truck ang bigas. Magbabayad si Aling Rosa sa checker. Sa Deliveries board, mamarkahan mong naihatid ang order at ilalagay ang halagang nasingil.',
                'For Turnover na ang order: bayad at naihatid na, pero nasa checker pa ang pera.',
                'Pagbalik sa opisina, iaabot ng checker ang pera sa sales rep, at itatala ang pag-abot. Magiging Closed ang order.',
            ],
        },
        words: [
            ['COD', 'Cash on delivery. Magbabayad ang customer pagdating ng produkto.'],
            ['Credit', 'Babayaran ng customer sa susunod, bago ang due date.'],
            ['AR Invoice', 'Ang singil para sa isang order. Makikita rito kung magkano pa ang utang.'],
            ['Receipt', 'Ang tala ng bayad na ginawa ng customer.'],
            ['Remittance', 'Ang pagpasok ng nasingil na pera sa opisina, kasama ang listahan ng sakop nito.'],
            ['For Turnover', 'Bayad at naihatid na, pero hindi pa nakakarating sa opisina ang pera.'],
        ],
    },
    inventory: {
        inShort: [
            'Dito ka bumibili ng stock at nagbibilang nito.',
            'Hihiling kang bumili, may mag-aapruba, darating ang produkto, at ire-receive mo sa bodega.',
            'Ang na-receive lang na stock ang puwedeng ibenta.',
        ],
        example: {
            title: 'Kailangan ng bodega ng 200 sako mula sa supplier',
            steps: [
                'Gagawa ka ng purchase request para sa 200 sako mula sa supplier.',
                'Aaprubahan ito ng approver. Magiging Purchase Order ito at magkakaroon ng PO number.',
                'Darating ang truck na may 150 sako. Ire-receive mo ang 150 laban sa PO. Nasa stock na ang mga ito at puwede nang ibenta.',
                'Darating ang natitirang 50 sa susunod na linggo. Ire-receive mo rin, at Completed na ang PO.',
                'May limang sakong sira. Gagawa ka ng stock return, at kapag naaprubahan, itatala mo kung ano ang pinalitan ng supplier.',
            ],
        },
        words: [
            ['Purchase Request (PR)', 'Paghingi ng pahintulot na bumili ng stock.'],
            ['Purchase Order (PO)', 'Request na naaprubahan na. Ito ang ipinapadala sa supplier.'],
            ['Receiving', 'Pagtatala na dumating na talaga ang produkto.'],
            ['Batch', 'Isang dating ng produkto, na may sariling puhunan at presyo.'],
            ['Minimum stock', 'Ang dami na kapag bumaba pa, pagsasabihan ka ng sistema na bumili.'],
            ['Stock Return', 'Pagbabalik ng sira o maling produkto sa supplier.'],
        ],
    },
    payroll: {
        inShort: [
            'Dito kinukuwenta at binabayaran ang sahod ng mga empleyado.',
            'Ihahanda mo ang payroll, may mag-aapruba, saka ire-release kapag lalabas na ang sahod.',
            'Kapag na-release na, naka-lock na ito, kaya suriin muna ang mga numero.',
        ],
        example: {
            title: 'Pagpapasahod sa mga taga-bodega para sa unang kalahati ng buwan',
            steps: [
                'Naka-save na ang mga taga-bodega bilang isang Payroll Group.',
                'I-click mo ang Add Payroll, piliin ang grupong iyon, at itakda ang period: ika-1 hanggang ika-15.',
                'Sa bawat tao, ilalagay mo ang araw na ipinasok. Si Carlo ay pumasok ng 13 araw sa ₱500 kada araw, kaya ₱6,500 ang basic pay niya.',
                'May 4 na oras din siyang overtime at ₱500 na cash advance na babayaran. Idadagdag mo ang mga iyon bilang earning at deduction.',
                'I-click mo ang Submit for Approval. Susuriin ng approver at aaprubahan. Sa araw ng sahod, ire-release ito at ipi-print mo ang mga payslip.',
            ],
        },
        words: [
            ['Payroll Group', 'Ang mga empleyadong sabay pinapasahod sa isang payroll.'],
            ['Pay period', 'Ang mga petsang sakop ng sahod, gaya ng ika-1 hanggang ika-15.'],
            ['Daily rate', 'Ang kinikita ng empleyado sa isang araw. Galing ito sa kanyang position.'],
            ['Earnings', 'Anumang dagdag sa basic pay: overtime, allowance.'],
            ['Deductions', 'Anumang bawas: kontribusyon, cash advance, bayad sa loan.'],
            ['Net pay', 'Ang aktuwal na maiuuwi ng empleyado.'],
            ['Release', 'Pagmamarka na naibayad na ang payroll. Hindi na ito mababawi.'],
        ],
    },
    employees: {
        inShort: [
            'Ito ang listahan ng lahat ng nagtatrabaho rito.',
            'May position ang bawat tao, at ang position ang nagtatakda ng arawang sahod.',
            'Kapag may umalis, gawin siyang inactive. Huwag burahin.',
        ],
        example: {
            title: 'May bagong cashier, si Bianca, na magsisimula sa Lunes',
            steps: [
                'Bubuksan mo ang Employees at magdadagdag ng bago.',
                'Ilalagay mo ang pangalan, kaarawan, contact number at address niya.',
                'Itatakda mo ang Position niya na Cashier. Gagamitin na ng payroll ang daily rate ng cashier para sa kanya.',
                'Kailangan niyang mag-sign in, kaya sa Users ay gagawan siya ng account at bibigyan ng tamang role.',
            ],
        },
        words: [
            ['Position', 'Ang trabaho ng tao. Ito ang nagtatakda ng daily rate niya sa payroll.'],
            ['Regular', 'Tanda ng regular na empleyado, hindi bago o pansamantala.'],
            ['Inactive', 'Hindi na nagtatrabaho rito. Nakatago ang record sa pang-araw-araw na listahan pero hindi nawawala.'],
            ['User account', 'Ang nagpapahintulot sa empleyado na mag-sign in. Hiwalay itong ginagawa, sa Users.'],
        ],
    },
    customers: {
        inShort: [
            'Ito ang listahan ng mga tao at tindahang binebentahan ninyo.',
            'Puwedeng bigyan ng credit limit ang customer: ang pinakamalaking puwede niyang utangin.',
            'Laging mag-search muna, para hindi madoble ang customer.',
        ],
        example: {
            title: 'May bagong tindahang gustong umutang',
            steps: [
                'Hahanapin mo ang pangalan ng tindahan para matiyak na wala pa ito sa listahan.',
                'Wala pa, kaya idadagdag mo ito kasama ang pangalan ng may-ari, address at contact number.',
                'Pumayag ang manager na puwede silang umutang ng hanggang ₱50,000, kaya iyon ang ilalagay mong credit limit.',
                'Sa susunod, kapag lalampas na sila sa ₱50,000 dahil sa isang credit order, pipigilan ng sistema ang order.',
            ],
        },
        words: [
            ['Credit limit', 'Ang pinakamalaking puwedeng utangin ng customer sa isang pagkakataon.'],
            ['Regular', 'Customer na madalas bumili.'],
            ['Blacklisted', 'Babala: mag-ingat bago magbenta sa customer na ito.'],
            ['Walk-in', 'Minsanang bumibili na hindi naka-save bilang customer.'],
        ],
    },
    accounting: {
        inShort: [
            'Ito ang bahagi ng pera: ano ang pumasok, ano ang lumabas, at ano ang natira.',
            'Karamihan dito ay kusang napupunan mula sa sales, resibo, payroll at gastos.',
            'Ang mali ay itinatama sa pagbaligtad ng entry, hindi sa pagbura.',
        ],
        example: {
            title: 'Pagbabayad ng ₱2,000 na cash para sa pagpapagawa ng truck',
            steps: [
                'Itatala mo ang gastos: Pagpapagawa ng truck, ₱2,000, cash ang bayad, kasama ang resibo.',
                'Naka-save ito bilang Draft at naghihintay ng approver.',
                'Bubuksan ito ng approver. Titingnan ng sistema kung may hindi bababa sa ₱2,000 na hawak na cash.',
                'Aaprubahan ng approver. Ang sistema na ang gagawa ng journal entry, at bababa ng ₱2,000 ang balanse ng cash.',
            ],
        },
        words: [
            ['Journal entry', 'Isang naitalang galaw ng pera, kung saan galing at saan napunta.'],
            ['Chart of accounts', 'Ang listahan ng mga kategoryang pinaglalagyan ng pera.'],
            ['Petty cash', 'Maliit na pondo ng cash para sa maliliit na gastos.'],
            ['Replenishment', 'Pagdaragdag ulit sa petty cash matapos itong magastos.'],
            ['Reverse', 'Pagkansela ng entry sa pamamagitan ng pagtatala ng kabaligtaran nito, para manatili ang kasaysayan.'],
            ['Trial balance', 'Report na tumitingin kung tugma ang mga libro.'],
        ],
    },
    user_management: {
        inShort: [
            'Dito pinagpapasyahan kung sino ang puwedeng mag-sign in.',
            'Ang role ay isang hanay ng mga pahintulot. Ibigay sa tao ang mga role na tugma sa trabaho niya.',
            'Puwedeng higit sa isa ang role ng isang tao.',
        ],
        example: {
            title: 'Si Carlo, ang warehouse manager, ay mag-aasikaso rin ng mga delivery',
            steps: [
                'Bubuksan mo ang Users at ie-edit ang account ni Carlo.',
                'Sa Roles, mayroon na siyang Warehouse Manager. Idadagdag mo ang Logistic Coordinator.',
                'I-save mo. Nasa kanya pa rin ang lahat ng kaya niyang gawin dati.',
                'Kaya na rin niyang gawin ang pinapayagan sa Logistic Coordinator.',
            ],
        },
        words: [
            ['User', 'Account na puwedeng mag-sign in.'],
            ['Role', 'Pinangalanang hanay ng mga pahintulot, gaya ng Sales Rep o Accountant.'],
            ['Permission', 'Ang pinapayagang gawin ng isang role sa isang bahagi ng sistema.'],
            ['Deactivate', 'Pagpatay sa account nang hindi ito binubura.'],
            ['Super Admin', 'Ang role na kayang gawin ang lahat.'],
        ],
    },
    libraries: {
        inShort: [
            'Ito ang mga listahang pinagpipilian ng buong sistema.',
            'Minsan lang itong ise-set up; pagkatapos, pipili ka na lang.',
            'Ang produkto, halimbawa, ay dito pinipili tuwing ibebenta o bibilhin.',
        ],
        example: {
            title: 'Pagdaragdag ng bagong produkto: Jasmine rice, 25 kg na sako',
            steps: [
                'Titingnan mo kung may brand na sa Brands. Kung wala, idadagdag mo.',
                'Titingnan mo kung may "kg" sa Units at "Sack" sa Packagings.',
                'Sa Products, idadagdag mo ang bago: ang brand, 25, kg, Sack.',
                'Ilalagay mo ang minimum stock na 20, para masabihan ka kapag wala nang 20 ang natitira.',
                'Mapipili na ito sa mga purchase request at sales order.',
            ],
        },
        words: [
            ['Brand', 'Ang gumawa o tatak ng produkto.'],
            ['Unit', 'Kung paano ito sinusukat, gaya ng kg.'],
            ['Packaging', 'Kung paano ito nakabalot, gaya ng sako.'],
            ['Location', 'Lugar na pinaghahatiran ng produkto.'],
            ['Status', 'Ang tatak na nagpapakita kung nasaang yugto ang isang record.'],
        ],
    },
    approvals: {
        inShort: [
            'Tinitipon ng page na ito ang lahat ng naghihintay ng oo o hindi mo.',
            'Ang nakikita mo ay iyon lang mga pinapayagan kang aprubahan.',
            'Isang click para mag-approve o mag-reject, may remark kung gusto mo.',
        ],
        example: {
            title: 'May loan request na naghihintay sa iyo',
            steps: [
                'May bagong notification sa bell: may loan ng empleyado na naghihintay ng approval.',
                'I-click mo ito. Bubukas ang Approvals page at naka-highlight ang loan na iyon.',
                'Babasahin mo ang pangalan ng empleyado at ang halaga.',
                'I-click mo ang Approve, maglalagay ng maikling remark, at kukumpirmahin.',
                'Mawawala ang loan sa iyong listahan, at malilinis din ang notification ng ibang approver.',
            ],
        },
        words: [
            ['Pending', 'Naghihintay ng magpapasya.'],
            ['Approve', 'Oo. Lilipat ang record sa susunod na yugto.'],
            ['Reject', 'Hindi. Babalik o hihinto ang record.'],
            ['Review', 'Buksan ang record sa sariling module nito, dahil kailangan pa ng detalye.'],
            ['Remarks', 'Maikling paliwanag ng iyong desisyon.'],
        ],
    },
    dashboard: {
        inShort: [
            'Dinadala ka ng menu sa kaliwa sa bawat bahagi ng sistema.',
            'Sinasabi ng bell kapag may kailangan sa iyo.',
            'Isang click lang ang aklat na ito, saanmang page ka naroon.',
        ],
        example: {
            title: 'Ang una mong limang minuto',
            steps: [
                'Mag-sign in. Mapupunta ka sa Dashboard, na nagpapakita ng mga numero ngayong araw.',
                'Tingnan ang menu sa kaliwa. Iyon ang mga bahaging puwede mong gamitin.',
                'I-click ang isa. May makikita kang listahan. Ang search box ay panghanap ng record; ang Sort by ay pang-ayos ng pagkakasunod.',
                'I-click ang icon na aklat sa itaas. Bubukas ang gabay na ito sa page na tinitingnan mo.',
                'I-click ang bell para makita ang anumang naghihintay sa iyo.',
            ],
        },
        words: [
            ['Module', 'Isang bahagi ng sistema, gaya ng Sales o Payroll.'],
            ['Record', 'Isang item sa listahan: isang order, isang empleyado, isang payroll.'],
            ['Status', 'Ang tatak sa record na nagsasabi kung nasaang yugto ito.'],
            ['Draft', 'Naka-save pero hindi pa tapos o naipapasa.'],
            ['Notification', 'Mensahe mula sa sistema na may kailangan ng iyong pansin.'],
        ],
    },
};
