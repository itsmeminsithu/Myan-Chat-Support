# Brand Logo ထည့်ရန်

1. **Admin → Settings → Brand Logo** ကို ဖွင့်ပါ။
2. **Choose logo file** မှာ PNG, JPEG သို့မဟုတ် WebP ရွေးပါ။
3. File က 4 MB အောက်၊ အရွယ်အစား 16×16 မှ 4096×4096 pixels အတွင်း ဖြစ်ရပါမယ်။
4. **Upload Logo** နှိပ်ပါ။ Dashboard Sidebar နဲ့ Login Screen မှာ ပြပါမယ်။
5. Guest Demo ကို Reload လုပ်ပြီး Chat Button နဲ့ Header ကို စစ်ပါ။

Logo ကို Docker ရဲ့ `app_storage` Volume မှာ သိမ်းပါတယ်။ Container Restart လုပ်လည်း မပျက်ပါဘူး။ `docker compose down -v` မသုံးပါနဲ့။

Developer Credit: **Developed by Min SiThu**
