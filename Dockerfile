# ১. উবুন্টু বেস ইমেজ ব্যবহার
FROM ubuntu:22.04

# ইন্টারনাল প্রম্পট বন্ধ করা
ENV DEBIAN_FRONTEND=noninteractive

# ২. প্রয়োজনীয় প্যাকেজ এবং Apache + PHP + Python ইনস্টল করা
RUN apt-get update && apt-get install -y \
    curl \
    git \
    software-properties-common \
    apache2 \
    php \
    libapache2-mod-php \
    python3 \
    python3-pip \
    python3-venv \
    && rm -rf /var/lib/apt/lists/*

# ৩. Node.js ইনস্টল করা
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# ৪. অ্যাপাচির ওয়ার্কিং ডিরেক্টরি সেট করা
WORKDIR /var/www/html

# 🔴 ৫. অ্যাপাচির তৈরি করা ডিফল্ট index.html ফাইলটি পার্মানেন্টলি ডিলিট করা
# এর ফলে অ্যাপাচির ডিফল্ট পেজ আর কখনোই আপনার সামনে আসবে না
RUN rm -f index.html

# 🔴 ৬. আপনার GitHub রুট রেপোর সব ফাইল সরাসরি এখানে কপি করা
# আপনি রেপোতে যা রাখবেন হুবহু সেটাই এখানে আসবে, যা ডিলিট করবেন তাও এখান থেকে হাওয়া হয়ে যাবে
COPY . .

# 7. পোর্ট এক্সপোজ করা
EXPOSE 80

# ৮. সার্ভার চালু করার কমান্ড
CMD ["apachectl", "-D", "FOREGROUND"]
