<footer class="bg-gray-800 text-white py-4 mt-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-center">
                <p class="text-center" id="copyright-year">&copy; <span id="year">2023</span> Dissertation Management System</p>
            </div>
        </div>
    </footer>

    <script>
        // Get the current year
        const currentYear = new Date().getFullYear();

        // Set the year in the copyright notice
        document.getElementById('year').textContent = currentYear;
    </script>
</body>
</html>